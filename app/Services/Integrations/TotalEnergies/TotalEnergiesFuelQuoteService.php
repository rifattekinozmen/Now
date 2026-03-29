<?php

namespace App\Services\Integrations\TotalEnergies;

use Illuminate\Support\Facades\Http;

/**
 * TotalEnergies yakıt / fiyat entegrasyonu.
 *
 * Sözleşme özeti: `config('totalenergies.schema_version')` (1); ayrıştırma
 * {@see TotalEnergiesResponseParser}. `price_eur_per_liter` anahtarı geçmiş uyumluluk için
 * birim başına sayısal fiyatı taşır (para birimi `currency` ile birlikte değerlendirilmelidir).
 */
final class TotalEnergiesFuelQuoteService
{
    public function __construct(
        private readonly bool $enabled,
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly string $quotePath,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (bool) config('totalenergies.enabled', false),
            config('totalenergies.api_key') !== null ? (string) config('totalenergies.api_key') : null,
            (string) config('totalenergies.base_url', ''),
            (string) config('totalenergies.quote_path', '/diesel-quote'),
        );
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     price_eur_per_liter: float|null,
     *     currency?: string|null,
     *     location_label?: string|null,
     *     raw?: mixed
     * }
     */
    public function fetchSampleDieselQuote(): array
    {
        if (! $this->enabled || $this->apiKey === null || $this->apiKey === '') {
            return [
                'ok' => false,
                'message' => __('TotalEnergies integration is disabled or API key is missing.'),
                'price_eur_per_liter' => null,
                'currency' => null,
                'location_label' => null,
            ];
        }

        $base = rtrim(trim($this->baseUrl), '/');
        $path = str_starts_with($this->quotePath, '/') ? $this->quotePath : '/'.$this->quotePath;
        $url = $base.$path;

        if ($base === '' || $base === 'https://api.totalenergies.example') {
            return [
                'ok' => false,
                'message' => __('TotalEnergies base URL is not configured.'),
                'price_eur_per_liter' => null,
                'currency' => null,
                'location_label' => null,
            ];
        }

        try {
            $response = Http::timeout((int) config('totalenergies.timeout_seconds', 15))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-API-Key' => $this->apiKey,
                ])
                ->get($url, $this->quoteQueryParams());
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'price_eur_per_liter' => null,
                'currency' => null,
                'location_label' => null,
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => __('TotalEnergies request failed: :status', ['status' => $response->status()]),
                'price_eur_per_liter' => null,
                'currency' => null,
                'location_label' => null,
            ];
        }

        $json = $response->json();
        $price = null;
        $currency = null;
        $locationLabel = null;
        if (is_array($json)) {
            $parsed = TotalEnergiesResponseParser::fromConfig()->parse($json);
            $price = $parsed['price'];
            $currency = $parsed['currency'];
            $locationLabel = $parsed['location'];
        }

        if ($price === null) {
            return [
                'ok' => false,
                'message' => __('Unexpected TotalEnergies response shape.'),
                'price_eur_per_liter' => null,
                'currency' => null,
                'location_label' => null,
                'raw' => $json,
            ];
        }

        if ($currency === null || $currency === '') {
            $default = config('totalenergies.default_currency');
            $currency = is_string($default) && $default !== '' ? strtoupper($default) : 'EUR';
        }

        return [
            'ok' => true,
            'message' => __('Quote retrieved.'),
            'price_eur_per_liter' => (float) $price,
            'currency' => $currency,
            'location_label' => $locationLabel,
            'raw' => $json,
        ];
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return array<string, scalar>
     */
    private function quoteQueryParams(): array
    {
        $extra = config('totalenergies.quote_query');
        $merged = is_array($extra) ? $extra : [];

        $merged['region'] ??= config('totalenergies.default_region', 'TR');

        /** @var array<string, scalar> $out */
        $out = [];
        foreach ($merged as $k => $v) {
            if (is_string($k) && (is_scalar($v) || $v === null)) {
                $out[$k] = $v ?? '';
            }
        }

        return $out;
    }
}
