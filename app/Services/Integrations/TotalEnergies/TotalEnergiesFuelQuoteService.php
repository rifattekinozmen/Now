<?php

namespace App\Services\Integrations\TotalEnergies;

/**
 * TotalEnergies yakıt / fiyat entegrasyonu için iskelet.
 *
 * Gerçek API sözleşmesi ve kimlik bilgisi olmadan güvenli varsayılan: devre dışı / boş yanıt.
 */
final class TotalEnergiesFuelQuoteService
{
    public function __construct(
        private readonly bool $enabled,
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (bool) config('totalenergies.enabled', false),
            config('totalenergies.api_key') !== null ? (string) config('totalenergies.api_key') : null,
            (string) config('totalenergies.base_url', ''),
        );
    }

    /**
     * @return array{ok: bool, message: string, price_eur_per_liter: float|null}
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

        return [
            'ok' => false,
            'message' => __('TotalEnergies API is not implemented yet (stub).'),
            'price_eur_per_liter' => null,
        ];
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
