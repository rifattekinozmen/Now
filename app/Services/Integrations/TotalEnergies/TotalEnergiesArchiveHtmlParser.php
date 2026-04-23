<?php

namespace App\Services\Integrations\TotalEnergies;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * TotalEnergies fiyat arşivi HTML tablosunu satır bazlı normalize eder.
 *
 * @phpstan-type ParsedArchiveRow array{
 *     recorded_at: string,
 *     prices: array{diesel?: float, gasoline?: float, lpg?: float}
 * }
 */
final class TotalEnergiesArchiveHtmlParser
{
    /**
     * @param  array<string, list<string>>  $columnMap
     * @return list<ParsedArchiveRow>
     */
    public function parse(string $html, array $columnMap): array
    {
        if (trim($html) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        if (! $loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $table = $xpath->query('//table[1]')->item(0);
        if (! $table instanceof \DOMElement) {
            return [];
        }

        $headers = [];
        $headerNodes = $xpath->query('.//thead//th', $table);
        if ($headerNodes !== false) {
            foreach ($headerNodes as $headerNode) {
                $headers[] = $this->normalizeWhitespace($headerNode->textContent);
            }
        }

        $indexes = $this->resolveColumnIndexes($headers, $columnMap);
        if ($indexes === []) {
            return [];
        }

        $rows = [];
        $bodyRows = $xpath->query('.//tbody//tr', $table);
        if ($bodyRows === false) {
            return [];
        }

        foreach ($bodyRows as $bodyRow) {
            $cells = $xpath->query('./th|./td', $bodyRow);
            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $dateText = $this->normalizeWhitespace((string) $cells->item(0)?->textContent);
            $recordedAt = $this->parseDate($dateText);
            if ($recordedAt === null) {
                continue;
            }

            $prices = [];
            foreach ($indexes as $fuelType => $index) {
                $cell = $cells->item($index);
                $value = $cell instanceof \DOMNode
                    ? $this->normalizeFloat($this->normalizeWhitespace($cell->textContent))
                    : null;

                if ($value !== null) {
                    $prices[$fuelType] = $value;
                }
            }

            if ($prices === []) {
                continue;
            }

            $rows[] = [
                'recorded_at' => $recordedAt,
                'prices' => $prices,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, list<string>>  $columnMap
     * @return array<string, int>
     */
    private function resolveColumnIndexes(array $headers, array $columnMap): array
    {
        $indexes = [];
        $normalizedHeaders = array_map(function (string $header): string {
            return Str::lower(Str::ascii($header));
        }, $headers);

        foreach ($columnMap as $fuelType => $keywords) {
            if (! is_string($fuelType) || ! in_array($fuelType, ['diesel', 'gasoline', 'lpg'], true)) {
                continue;
            }

            foreach ($normalizedHeaders as $index => $header) {
                foreach ($keywords as $keyword) {
                    if (! is_string($keyword) || $keyword === '') {
                        continue;
                    }

                    $normalizedKeyword = Str::lower(Str::ascii($keyword));
                    if (str_contains($header, $normalizedKeyword)) {
                        $indexes[$fuelType] = $index;
                        break 2;
                    }
                }
            }
        }

        return $indexes;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $turkishMonths = [
            'Ocak' => 'January',
            'Şubat' => 'February',
            'Mart' => 'March',
            'Nisan' => 'April',
            'Mayıs' => 'May',
            'Haziran' => 'June',
            'Temmuz' => 'July',
            'Ağustos' => 'August',
            'Eylül' => 'September',
            'Ekim' => 'October',
            'Kasım' => 'November',
            'Aralık' => 'December',
        ];

        $normalized = strtr($value, $turkishMonths);

        try {
            return CarbonImmutable::parse($normalized)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeWhitespace(string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function normalizeFloat(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $value = str_replace([' ', "\xc2\xa0"], '', $value);
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
