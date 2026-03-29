<?php

namespace App\Services\Integrations\TotalEnergies;

use Illuminate\Support\Facades\Http;

/**
 * TotalEnergies yakıt / fiyat entegrasyonu.
 *
 * Gerçek API şekli müşteri sözleşmesine göre değişebilir; yanıtta `price_eur_per_liter`
 * veya `data.price` beklenir. Uç nokta `config('totalenergies.quote_path')` ile birleştirilir.
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
     * @return array{ok: bool, message: string, price_eur_per_liter: float|null, raw?: mixed}
     */
    public function fetchSampleDieselQuote(): array
    {
        if (! $this->enabled || $this->apiKey === null || $this->apiKey === '') {
            return [
                'ok' => false,
                'message' => __('TotalEnergies integration is disabled or API key is missing.'),
                'price_eur_per_liter' => null,
            ];
        }

        $base = rtrim($this->baseUrl, '/');
        $path = str_starts_with($this->quotePath, '/') ? $this->quotePath : '/'.$this->quotePath;
        $url = $base.$path;

        if ($base === '' || $base === 'https://api.totalenergies.example') {
            return [
                'ok' => false,
                'message' => __('TotalEnergies base URL is not configured.'),
                'price_eur_per_liter' => null,
            ];
        }

        try {
            $response = Http::timeout((int) config('totalenergies.timeout_seconds', 15))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-API-Key' => $this->apiKey,
                ])
                ->get($url, [
                    'region' => config('totalenergies.default_region', 'TR'),
                ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'price_eur_per_liter' => null,
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => __('TotalEnergies request failed: :status', ['status' => $response->status()]),
                'price_eur_per_liter' => null,
            ];
        }

        $json = $response->json();
        $price = null;
        if (is_array($json)) {
            $paths = config('totalenergies.response_price_paths');
            if (! is_array($paths) || $paths === []) {
                $paths = ['price_eur_per_liter', 'data.price'];
            }
            foreach ($paths as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }
                $candidate = data_get($json, $path);
                if (is_numeric($candidate)) {
                    $price = $candidate;
                    break;
                }
            }
        }

        if (! is_numeric($price)) {
            return [
                'ok' => false,
                'message' => __('Unexpected TotalEnergies response shape.'),
                'price_eur_per_liter' => null,
                'raw' => $json,
            ];
        }

        return [
            'ok' => true,
            'message' => __('Quote retrieved.'),
            'price_eur_per_liter' => (float) $price,
            'raw' => $json,
        ];
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
