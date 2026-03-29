<?php

namespace App\Services\Finance;

use App\Contracts\Finance\ScannedPdfOcrAdapter;

/**
 * OCR yok: depoda yer tutucu; gerçek OCR için sınıfı değiştirin veya container bağlayın.
 */
final class NullScannedPdfOcrAdapter implements ScannedPdfOcrAdapter
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function extractPlainText(string $absolutePathToPdf): ?string
    {
        return null;
    }
}
