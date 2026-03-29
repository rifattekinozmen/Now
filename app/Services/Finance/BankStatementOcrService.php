<?php

namespace App\Services\Finance;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Banka ekstresi: PDF metin çıkarımı (Smalot PdfParser) ve CSV satır çıkarımı.
 *
 * Görüntü tabanlı taranmış PDF’ler için OCR ayrıca gerekir; bu sınıf metin katmanını okur.
 * Taranmış PDF kullanıcı mesajı: {@see self::pdfImportDiagnosticMessage()} ve `empty_text` anahtarı.
 */
class BankStatementOcrService
{
    /**
     * PDF içinde seçilebilir metin katmanı varsa satır çıkarımı yapılabilir.
     */
    public function pdfTextLayerExtractionSupported(): bool
    {
        return true;
    }

    /**
     * Taranmış görüntü / görüntü-only PDF için harici OCR entegrasyonu yoktur.
     */
    public function scannedImageOcrSupported(): bool
    {
        return false;
    }

    /**
     * PDF dosyasından metin çıkarır ve satır desenlerine göre ayrıştırır.
     *
     * @return list<array{booked_at: string|null, amount: string|null, description: string|null}>
     */
    public function extractRowsFromPdf(string $absolutePath, int $maxRows = 500): array
    {
        return $this->extractRowsFromPdfWithDiagnostics($absolutePath, $maxRows)['rows'];
    }

    /**
     * @return array{rows: list<array{booked_at: string|null, amount: string|null, description: string|null}>, diagnostic: string}
     */
    public function extractRowsFromPdfWithDiagnostics(string $absolutePath, int $maxRows = 500): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return ['rows' => [], 'diagnostic' => 'unreadable_file'];
        }

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($absolutePath);
            $text = $pdf->getText();
        } catch (Throwable) {
            return ['rows' => [], 'diagnostic' => 'parse_error'];
        }

        $text = trim((string) $text);
        if ($text === '') {
            return ['rows' => [], 'diagnostic' => 'empty_text'];
        }

        $rows = $this->extractRowsFromStatementPlainText($text, $maxRows);
        if ($rows === []) {
            return ['rows' => [], 'diagnostic' => 'no_matching_lines'];
        }

        return ['rows' => $rows, 'diagnostic' => 'ok'];
    }

    /**
     * PDF içe aktarma hatası için kullanıcıya gösterilecek çevrilmiş mesaj (UI ile aynı kaynak).
     */
    public function pdfImportDiagnosticMessage(string $diagnostic): string
    {
        return match ($diagnostic) {
            'empty_text' => __('This PDF has no extractable text (likely a scanned image). Export CSV from your bank or use OCR in a later release.'),
            'no_matching_lines' => __('Text was found but no lines matched the expected format (date at start, amount at end). Try CSV export or check the sample line format below.'),
            'unreadable_file' => __('The uploaded file could not be read.'),
            'parse_error' => __('The PDF could not be parsed. It may be corrupted or password-protected.'),
            default => __('No transaction lines were parsed from this PDF. Try a CSV export from your bank, or use a PDF with a selectable text layer (image-only scans need separate OCR).'),
        };
    }

    /**
     * PDF veya başka kaynaktan gelen düz metni (ekstre satırları) ayrıştırır.
     *
     * @return list<array{booked_at: string|null, amount: string|null, description: string|null}>
     */
    public function extractRowsFromStatementPlainText(string $text, int $maxRows = 500): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        if ($lines === false) {
            return [];
        }

        $out = [];
        foreach ($lines as $line) {
            if (count($out) >= $maxRows) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseStatementTextLine($line);
            if ($parsed === null) {
                continue;
            }
            if ($this->rowIsEmpty($parsed)) {
                continue;
            }
            $out[] = $parsed;
        }

        return $out;
    }

    /**
     * UTF-8 CSV içeriğinden tarih / tutar / açıklama kolonlarını çıkarır (TR ve EN başlık aliasları).
     *
     * @return list<array{booked_at: string|null, amount: string|null, description: string|null}>
     */
    public function extractRowsFromCsvContent(string $csvContent, int $maxRows = 500): array
    {
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent) ?? $csvContent;
        $csvContent = trim($csvContent);
        if ($csvContent === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $csvContent);
        if ($lines === false || $lines === []) {
            return [];
        }

        $headerCells = str_getcsv(array_shift($lines));
        $map = $this->resolveColumnMap($headerCells);

        $out = [];
        foreach ($lines as $line) {
            if (count($out) >= $maxRows) {
                break;
            }
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $row = $this->cellsToRow($cells, $map);
            if ($this->rowIsEmpty($row)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  list<string>  $headers
     * @return array{date: int|null, amount: int|null, description: int|null}
     */
    private function resolveColumnMap(array $headers): array
    {
        $map = ['date' => null, 'amount' => null, 'description' => null];
        foreach ($headers as $i => $raw) {
            $key = $this->normalizeHeaderKey((string) $raw);
            if ($key === '') {
                continue;
            }
            if ($map['date'] === null && $this->headerIsDate($key)) {
                $map['date'] = $i;
            }
            if ($map['amount'] === null && $this->headerIsAmount($key)) {
                $map['amount'] = $i;
            }
            if ($map['description'] === null && $this->headerIsDescription($key)) {
                $map['description'] = $i;
            }
        }

        if ($map['date'] === null && isset($headers[0])) {
            $map['date'] = 0;
        }
        if ($map['amount'] === null && isset($headers[1])) {
            $map['amount'] = 1;
        }
        if ($map['description'] === null && isset($headers[2])) {
            $map['description'] = 2;
        }

        return $map;
    }

    private function normalizeHeaderKey(string $raw): string
    {
        $s = mb_strtolower(trim($raw));

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    private function headerIsDate(string $key): bool
    {
        return match (true) {
            str_contains($key, 'tarih') => true,
            str_contains($key, 'date') => true,
            $key === 'dt' => true,
            default => false,
        };
    }

    private function headerIsAmount(string $key): bool
    {
        return match (true) {
            str_contains($key, 'tutar') => true,
            str_contains($key, 'amount') => true,
            str_contains($key, 'borç') => true,
            str_contains($key, 'alacak') => true,
            str_contains($key, 'debit') => true,
            str_contains($key, 'credit') => true,
            default => false,
        };
    }

    private function headerIsDescription(string $key): bool
    {
        return match (true) {
            str_contains($key, 'açıklama') => true,
            str_contains($key, 'aciklama') => true,
            str_contains($key, 'description') => true,
            str_contains($key, 'detail') => true,
            str_contains($key, 'işlem') => true,
            default => false,
        };
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array{date: int|null, amount: int|null, description: int|null}  $map
     * @return array{booked_at: string|null, amount: string|null, description: string|null}
     */
    private function cellsToRow(array $cells, array $map): array
    {
        $dateRaw = $this->cellAt($cells, $map['date']);
        $amountRaw = $this->cellAt($cells, $map['amount']);
        $descRaw = $this->cellAt($cells, $map['description']);

        return [
            'booked_at' => $this->normalizeDate($dateRaw),
            'amount' => $this->normalizeAmount($amountRaw),
            'description' => $descRaw !== null && $descRaw !== '' ? $descRaw : null,
        ];
    }

    /**
     * @param  list<string|null>  $cells
     */
    private function cellAt(array $cells, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $cells)) {
            return null;
        }

        $v = $cells[$index];
        if ($v === null) {
            return null;
        }
        $t = trim((string) $v);

        return $t === '' ? null : $t;
    }

    /**
     * Tek satır: başta tarih, sonda tutar (TR/EN); arada açıklama.
     *
     * @return array{booked_at: string|null, amount: string|null, description: string|null}|null
     */
    private function parseStatementTextLine(string $line): ?array
    {
        if (preg_match('/^\s*(?P<date>\d{4}-\d{2}-\d{2}|\d{2}\.\d{2}\.\d{4})\s+(?P<rest>.+)$/u', $line, $m) !== 1) {
            return null;
        }

        $rest = trim($m['rest']);
        if ($rest === '') {
            return null;
        }

        if (preg_match('/^(?P<mid>.+?)\s+(?P<amt>[-+]?\d[\d\s\.,\p{Zs}]*\d)\s*$/u', $rest, $m2) === 1) {
            $desc = trim($m2['mid']);

            return [
                'booked_at' => $this->normalizeDate($m['date']),
                'amount' => $this->normalizeAmount($m2['amt']),
                'description' => $desc !== '' ? $desc : null,
            ];
        }

        if (preg_match('/^(?P<amt>[-+]?\d[\d\s\.,\p{Zs}]*\d)\s*$/u', $rest, $m3) === 1) {
            return [
                'booked_at' => $this->normalizeDate($m['date']),
                'amount' => $this->normalizeAmount($m3['amt']),
                'description' => null,
            ];
        }

        return null;
    }

    /**
     * @param  array{booked_at: string|null, amount: string|null, description: string|null}  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        return $row['booked_at'] === null && $row['amount'] === null && $row['description'] === null;
    }

    private function normalizeDate(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) === 1) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $raw, $m) === 1) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return null;
    }

    private function normalizeAmount(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = str_replace([' ', "\xc2\xa0"], '', $raw);
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

        return number_format((float) $s, 2, '.', '');
    }
}
