<?php

namespace App\Services\Logistics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

/**
 * TCMB günlük döviz (today.xml) — hukuki/finansal tavsiye değildir; operasyonel kur önbelleği.
 */
final class TcmbExchangeRateService
{
    public const string TCMB_TODAY_XML_URL = 'https://www.tcmb.gov.tr/kurlar/today.xml';

    public const string CACHE_KEY_RATES = 'logistics.tcmb.rates';

    public const string CACHE_KEY_FETCHED_AT = 'logistics.tcmb.fetched_at';

    /**
     * Uzak sunucudan çekip önbelleğe yazar. Başarılı/başarısız bilgisini döner.
     */
    public function tryRefreshFromRemote(): bool
    {
        try {
            $rates = $this->fetchRatesFromRemote();
            $this->storeRates($rates);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string> ISO döviz kodu => ForexBuying (1 birim yabancı para için TL)
     */
    public function storedRates(): array
    {
        $raw = Cache::get(self::CACHE_KEY_RATES);

        return is_array($raw) ? $raw : [];
    }

    public function storedFetchedAt(): ?string
    {
        $at = Cache::get(self::CACHE_KEY_FETCHED_AT);

        return is_string($at) ? $at : null;
    }

    public function rateTryPerUnit(string $currencyCode): ?string
    {
        $code = strtoupper(trim($currencyCode));
        if ($code === '' || $code === 'TRY') {
            return '1';
        }

        $rates = $this->storedRates();

        return $rates[$code] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function parseRatesXml(string $xml): array
    {
        $element = @simplexml_load_string($xml);
        if (! $element instanceof SimpleXMLElement) {
            throw new \RuntimeException('TCMB XML ayrıştırılamadı.');
        }

        $rates = [];
        foreach ($element->Currency as $currency) {
            $code = strtoupper(trim((string) ($currency['CurrencyCode'] ?? $currency['Kod'] ?? '')));
            if ($code === '') {
                continue;
            }

            $buying = trim((string) $currency->ForexBuying);
            if ($buying === '' || $buying === '0') {
                continue;
            }

            $rates[$code] = str_replace(',', '.', $buying);
        }

        return $rates;
    }

    /**
     * @return array<string, string>
     */
    private function fetchRatesFromRemote(): array
    {
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'Now-Logistics/1.0'])
            ->get(self::TCMB_TODAY_XML_URL);

        $response->throw();

        return $this->parseRatesXml($response->body());
    }

    /**
     * @param  array<string, string>  $rates
     */
    private function storeRates(array $rates): void
    {
        Cache::put(self::CACHE_KEY_RATES, $rates, now()->addHours(6));
        Cache::put(self::CACHE_KEY_FETCHED_AT, now()->toIso8601String(), now()->addHours(6));
    }
}
