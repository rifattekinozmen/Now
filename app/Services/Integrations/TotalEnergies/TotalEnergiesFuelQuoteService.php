<?php

namespace App\Services\Integrations\TotalEnergies;

use Illuminate\Support\Facades\Http;

/**
 * TotalEnergies yakıt / fiyat entegrasyonu.
 *
 * Fiyat yolu `config('totalenergies.response_price_paths')` ile; ondalık string ("1,55")
 * normalize edilir. `price_eur_per_liter` anahtarı geçmiş uyumluluk için birim başına
 * sayısal fiyatı taşır (para birimi `currency` ile birlikte değerlendirilmelidir).
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
     * @return array{ok: bool, message: string, price_eur_per_liter: float|null, currency?: string|null, raw?: mixed}
     */
    public function fetchSampleDieselQuote(): array
    {
        if (! $this->enabled || $this->apiKey === null || $this->apiKey === '') {
            return [
                'ok' => false,
                'message' => __('TotalEnergies integration is disabled or API key is missing.'),
                'price_eur_per_liter' => null,
                'currency' => null,
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
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => __('TotalEnergies request failed: :status', ['status' => $response->status()]),
                'price_eur_per_liter' => null,
                'currency' => null,
            ];
        }

        $json = $response->json();
        $price = null;
        $currency = null;
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
                $normalized = $this->normalizeToFloat($candidate);
                if ($normalized !== null) {
                    $price = $normalized;
                    break;
                }
            }

            $currencyPaths = config('totalenergies.response_currency_paths');
            if (is_array($currencyPaths)) {
                foreach ($currencyPaths as $cPath) {
                    if (! is_string($cPath) || $cPath === '') {
                        continue;
                    }
                    $cVal = data_get($json, $cPath);
                    if (is_string($cVal) && trim($cVal) !== '') {
                        $currency = strtoupper(trim($cVal));
                        break;
                    }
                }
            }
        }

        if ($price === null) {
            return [
                'ok' => false,
                'message' => __('Unexpected TotalEnergies response shape.'),
                'price_eur_per_liter' => null,
                'currency' => null,
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

    private function normalizeToFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $s = trim($value);
        if ($s === '') {
            return null;
        }
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
        if (! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
