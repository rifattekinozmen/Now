<?php

namespace App\Services\Finance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Receipt / voucher image OCR service.
 *
 * Supported providers (config/services.php → 'ocr.provider'):
 *   - 'null'          — always returns null (default / no-op)
 *   - 'google_vision' — Google Cloud Vision API (requires services.google_vision.key)
 *
 * Returns an array with keys: date, amount, vat_amount (all nullable strings).
 *
 * @phpstan-type OcrResult array{date: string|null, amount: string|null, vat_amount: string|null}
 */
final class VoucherOcrService
{
    /**
     * @return OcrResult
     */
    public function parseReceiptImage(string $absoluteImagePath): array
    {
        $provider = config('services.ocr.provider', 'null');

        $text = match ($provider) {
            'google_vision' => $this->extractViaGoogleVision($absoluteImagePath),
            default => null,
        };

        if ($text === null || $text === '') {
            return ['date' => null, 'amount' => null, 'vat_amount' => null];
        }

        return $this->parseText($text);
    }

    public function isAvailable(): bool
    {
        $provider = config('services.ocr.provider', 'null');

        if ($provider === 'google_vision') {
            return ! empty(config('services.google_vision.key'));
        }

        return false;
    }

    /**
     * Parse plain text extracted from a receipt and return structured fields.
     *
     * @return OcrResult
     */
    public function parseText(string $text): array
    {
        return [
            'date' => $this->extractDate($text),
            'amount' => $this->extractAmount($text),
            'vat_amount' => $this->extractVatAmount($text),
        ];
    }

    private function extractViaGoogleVision(string $imagePath): ?string
    {
        $key = config('services.google_vision.key');
        if (empty($key)) {
            return null;
        }

        try {
            $imageContent = base64_encode((string) file_get_contents($imagePath));
            $response = Http::post(
                'https://vision.googleapis.com/v1/images:annotate?key='.$key,
                [
                    'requests' => [[
                        'image' => ['content' => $imageContent],
                        'features' => [['type' => 'TEXT_DETECTION']],
                    ]],
                ]
            );

            return $response->json('responses.0.fullTextAnnotation.text');
        } catch (\Throwable $e) {
            Log::warning('VoucherOcrService: Google Vision API error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function extractDate(string $text): ?string
    {
        // Match patterns like: 20/04/2026, 20.04.2026, 2026-04-20
        $patterns = [
            '/\b(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})\b/',
            '/\b(\d{4})[\/\.\-](\d{1,2})[\/\.\-](\d{1,2})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                try {
                    $raw = $matches[0];
                    $date = Carbon::parse(str_replace(['.', '/'], '-', $raw));

                    return $date->format('Y-m-d');
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private function extractAmount(string $text): ?string
    {
        $patterns = [
            '/(?:TOPLAM|TUTAR|TOTAL|AMOUNT|GENEL TOPLAM)[^\d]*(\d[\d\s.,]+)/ui',
            '/(\d{1,9}[.,]\d{2})\s*(?:TL|TRY|₺|USD|EUR)/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $this->normalizeAmount($matches[1]);
            }
        }

        return null;
    }

    private function extractVatAmount(string $text): ?string
    {
        $patterns = [
            '/(?:KDV|VAT|VERGI|TAX)[^\d]*(\d[\d\s.,]+)/ui',
            '/(?:%18|%20|%8)[^\d]*(\d[\d\s.,]+)/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $this->normalizeAmount($matches[1]);
            }
        }

        return null;
    }

    private function normalizeAmount(string $raw): string
    {
        $clean = preg_replace('/\s+/', '', $raw) ?? $raw;

        if (str_contains($clean, '.') && str_contains($clean, ',')) {
            $lastDot = strrpos($clean, '.');
            $lastComma = strrpos($clean, ',');

            if ($lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return number_format((float) $clean, 2, '.', '');
    }
}
