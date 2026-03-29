<?php

namespace App\Services\Finance;

/**
 * Banka ekstresi OCR ve satır çıkarımı (Faz 3 — iskelet).
 */
final class BankStatementOcrService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function extractRowsFromPdf(string $absolutePath): array
    {
        return [];
    }
}
