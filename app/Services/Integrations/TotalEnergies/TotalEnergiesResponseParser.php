<?php

namespace App\Services\Integrations\TotalEnergies;

/**
 * TotalEnergies JSON yanıtından fiyat / para birimi / konum çıkarımı.
 *
 * Sözleşme: `config('totalenergies.schema_version')` === 1
 * — `response_price_paths`, `response_currency_paths`, `response_location_paths`
 *   dot-notation dizileri; ilk geçerli değer kullanılır.
 *
 * @phpstan-type ParsedQuote array{price: float|null, currency: string|null, location: string|null}
 */
final class TotalEnergiesResponseParser
{
    /**
     * @param  list<string>  $pricePaths
     * @param  list<string>  $currencyPaths
     * @param  list<string>  $locationPaths
     */
    public function __construct(
        private array $pricePaths,
        private array $currencyPaths,
        private array $locationPaths,
    ) {}

    public static function fromConfig(): self
    {
        $pp = config('totalenergies.response_price_paths');
        $cp = config('totalenergies.response_currency_paths');
        $lp = config('totalenergies.response_location_paths');

        return new self(
            is_array($pp) ? array_values(array_filter($pp, 'is_string')) : [],
            is_array($cp) ? array_values(array_filter($cp, 'is_string')) : [],
            is_array($lp) ? array_values(array_filter($lp, 'is_string')) : [],
        );
    }

    /**
     * `config/totalenergies.php` içindeki JSON sözleşme sürümü (şu an 1).
     */
    public static function configuredSchemaVersion(): int
    {
        $v = config('totalenergies.schema_version', 1);

        return is_numeric($v) ? (int) $v : 1;
    }

    /**
     * @return ParsedQuote
     */
    public function parse(array $json): array
    {
        $paths = $this->pricePaths;
        if ($paths === []) {
            $paths = ['price_eur_per_liter', 'data.price'];
        }

        $price = null;
        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }
            $candidate = data_get($json, $path);
            $normalized = $this->normalizeToFloat($candidate);
            if ($normalized !== null) {
                $price = $normalized;
                break;
            }
        }

        $currency = null;
        foreach ($this->currencyPaths as $cPath) {
            if ($cPath === '') {
                continue;
            }
            $cVal = data_get($json, $cPath);
            if (is_string($cVal) && trim($cVal) !== '') {
                $currency = strtoupper(trim($cVal));
                break;
            }
        }

        $locationLabel = null;
        foreach ($this->locationPaths as $locPath) {
            if ($locPath === '') {
                continue;
            }
            $locVal = data_get($json, $locPath);
            if (is_string($locVal) && trim($locVal) !== '') {
                $locationLabel = trim($locVal);
                break;
            }
        }

        return [
            'price' => $price !== null ? (float) $price : null,
            'currency' => $currency,
            'location' => $locationLabel,
        ];
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
