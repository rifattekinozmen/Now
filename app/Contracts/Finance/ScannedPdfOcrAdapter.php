<?php

namespace App\Contracts\Finance;

use App\Services\Finance\NullScannedPdfOcrAdapter;

/**
 * Taranmış / görüntü-only PDF için harici OCR (Tesseract, bulut API vb.) adaptörü.
 *
 * Varsayılan: {@see NullScannedPdfOcrAdapter}
 */
interface ScannedPdfOcrAdapter
{
    /**
     * Bu adaptör üretimde kullanılabilir mi (ör. API anahtarı yapılandırılmış mı).
     */
    public function isAvailable(): bool;

    /**
     * PDF dosyasından düz metin çıkarır; desteklenmiyorsa veya boşsa null.
     */
    public function extractPlainText(string $absolutePathToPdf): ?string;
}
