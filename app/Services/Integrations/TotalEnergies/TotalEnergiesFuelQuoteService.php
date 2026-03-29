<?php

namespace App\Services\Integrations\TotalEnergies;

use Illuminate\Http\Client\PendingRequest;
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
     * Operatör / smoke test için istek özeti (API anahtarı maskelenir).
     *
     * @return array{
     *     http_method: string,
     *     url: string,
     *     query_params: array<string, scalar>,
     *     json_body: array<string, scalar>,
     *     headers: array<string, string>,
     *     schema_version_expected: int
     * }
     */
    public function describeQuoteRequest(): array
    {
        $base = rtrim(trim($this->baseUrl), '/');
        $path = str_starts_with($this->quotePath, '/') ? $this->quotePath : '/'.$this->quotePath;
        $method = strtolower((string) config('totalenergies.quote_http_method', 'get'));
        $query = $this->quoteQueryParams();
        $jsonBody = $method === 'post' ? $this->quotePostJsonBody() : [];
        $url = $base !== '' ? $base.$path : '';

        $apiKeyDisplay = ($this->apiKey !== null && $this->apiKey !== '') ? '***' : '(missing)';

        return [
            'http_method' => $method === 'post' ? 'POST' : 'GET',
            'url' => $url,
            'query_params' => $query,
            'json_body' => $jsonBody,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $apiKeyDisplay,
            ],
            'schema_version_expected' => TotalEnergiesResponseParser::configuredSchemaVersion(),
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     price_eur_per_liter: float|null,
     *     currency?: string|null,
     *     location_label?: string|null,
     *     schema_version?: int,
     *     contract_valid?: bool,
     *     contract_issues?: list<string>,
     *     response_schema_match?: bool|null,
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
                'schema_version' => TotalEnergiesResponseParser::configuredSchemaVersion(),
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
                'schema_version' => TotalEnergiesResponseParser::configuredSchemaVersion(),
            ];
        }

        try {
            $client = $this->buildHttpClient();
            $method = strtolower((string) config('totalenergies.quote_http_method', 'get'));
            $response = $method === 'post'
                ? $client->asJson()->post($url, $this->quotePostJsonBody())
                : $client->get($url, $this->quoteQueryParams());
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'price_eur_per_liter' => null,
                'currency' => null,
                'location_label' => null,
                'schema_version' => TotalEnergiesResponseParser::configuredSchemaVersion(),
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => __('TotalEnergies request failed: :status', ['status' => $response->status()]),
                'price_eur_per_liter' => null,
                'currency' => null,
                'location_label' => null,
                'schema_version' => TotalEnergiesResponseParser::configuredSchemaVersion(),
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
            $contract = is_array($json)
                ? TotalEnergiesQuoteContractValidator::validate($json, null)
                : TotalEnergiesQuoteContractValidator::validate([], null);

            return [
                'ok' => false,
                'message' => __('Unexpected TotalEnergies response shape.'),
                'price_eur_per_liter' => null,
                'currency' => null,
                'location_label' => null,
                'schema_version' => TotalEnergiesResponseParser::configuredSchemaVersion(),
                'contract_valid' => false,
                'contract_issues' => $contract['issues'],
                'response_schema_match' => $contract['schema_match'],
                'raw' => $json,
            ];
        }

        if ($currency === null || $currency === '') {
            $default = config('totalenergies.default_currency');
            $currency = is_string($default) && $default !== '' ? strtoupper($default) : 'EUR';
        }

        $contract = is_array($json)
            ? TotalEnergiesQuoteContractValidator::validate($json, (float) $price)
            : TotalEnergiesQuoteContractValidator::validate([], (float) $price);

        return [
            'ok' => true,
            'message' => __('Quote retrieved.'),
            'price_eur_per_liter' => (float) $price,
            'currency' => $currency,
            'location_label' => $locationLabel,
            'schema_version' => TotalEnergiesResponseParser::configuredSchemaVersion(),
            'contract_valid' => $contract['contract_valid'],
            'contract_issues' => $contract['issues'],
            'response_schema_match' => $contract['schema_match'],
            'raw' => $json,
        ];
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    private function buildHttpClient(): PendingRequest
    {
        $timeout = (int) config('totalenergies.timeout_seconds', 15);
        $retry = config('totalenergies.retry');
        $times = is_array($retry) && isset($retry['times']) ? (int) $retry['times'] : 2;
        $sleepMs = is_array($retry) && isset($retry['sleep_ms']) ? (int) $retry['sleep_ms'] : 100;

        $headers = [
            'Accept' => 'application/json',
            'X-API-Key' => $this->apiKey,
        ];
        $extra = config('totalenergies.extra_headers');
        if (is_array($extra)) {
            foreach ($extra as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $headers[$name] = $value;
                }
            }
        }

        $attempts = max(1, $times);

        return Http::timeout($timeout)
            ->retry($attempts, max(0, $sleepMs))
            ->withHeaders($headers);
    }

    /**
     * @return array<string, scalar>
     */
    private function quoteQueryParams(): array
    {
        $extra = config('totalenergies.quote_query');
        $merged = is_array($extra) ? $extra : [];

        $merged['region'] ??= config('totalenergies.default_region', 'TR');

        $province = config('totalenergies.province');
        if (is_string($province) && trim($province) !== '') {
            $merged['province'] ??= trim($province);
        }

        $district = config('totalenergies.district');
        if (is_string($district) && trim($district) !== '') {
            $merged['district'] ??= trim($district);
        }

        /** @var array<string, scalar> $out */
        $out = [];
        foreach ($merged as $k => $v) {
            if (is_string($k) && (is_scalar($v) || $v === null)) {
                $out[$k] = $v ?? '';
            }
        }

        return $out;
    }

    /**
     * POST / JSON gövde: `quote_query` + `quote_json_body` (sonraki öncelikli).
     *
     * @return array<string, scalar>
     */
    private function quotePostJsonBody(): array
    {
        $query = $this->quoteQueryParams();
        $extra = config('totalenergies.quote_json_body');
        if (! is_array($extra)) {
            return $query;
        }

        /** @var array<string, scalar> $merged */
        $merged = $query;
        foreach ($extra as $k => $v) {
            if (is_string($k) && (is_scalar($v) || $v === null)) {
                $merged[$k] = $v ?? '';
            }
        }

        return $merged;
    }
}
