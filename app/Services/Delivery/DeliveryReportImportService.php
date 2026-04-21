<?php

namespace App\Services\Delivery;

use App\Models\DeliveryImport;
use App\Models\DeliveryImportRow;
use App\Support\DeliveryImportPhp;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class DeliveryReportImportService
{
    /**
     * Excel dosyasını beklenen başlıklara göre normalize edip delivery_import_rows olarak kaydeder.
     *
     * @return array{total_rows: int, saved: int, errors: array<int, string>}
     */
    public function importAndSaveReportRows(DeliveryImport $import): array
    {
        if (! $import->file_path) {
            throw new Exception(__('No file attached to this import.'));
        }

        $path = Storage::disk('local')->path($import->file_path);

        if (! file_exists($path)) {
            throw new Exception("Dosya bulunamadı: {$import->file_path}");
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xlsm'], true) && ! DeliveryImportPhp::isZipAvailableForXlsx()) {
            throw new Exception(
                __('PHP zip (ZipArchive) .xlsx/.xlsm için gerekli. php.ini’de extension=zip açıp Apache’yi yeniden başlatın; liste sayfasında Active php.ini yolu görünür. Geçici çözüm: Excel’de “Farklı Kaydet” → “Excel 97-2003 Çalışma Kitabı (.xls)” olarak kaydedip yükleyin.')
            );
        }

        $import->reportRows()->delete();

        $expectedHeaders = $this->getExpectedHeadersForImport($import);
        $aliases = $this->getHeaderAliasesForImport($import);
        $result = $this->readSpreadsheetWithHeaders($path, $expectedHeaders, $aliases);

        $excelHeaders = $result['headers'];
        $excelRows = $result['rows'];
        $excelCalendar = $result['excel_calendar'] ?? null;
        $headerRow1Based = $result['header_row_1based'] ?? 1;
        $dateColumnExpectedIndices = $this->getDateColumnExpectedIndicesForImport($import);
        $timeColumnExpectedIndices = $this->getTimeColumnExpectedIndicesForImport($import);
        $numericColumnExpectedIndices = $this->getNumericColumnExpectedIndicesForImport($import);
        $mapping = $this->buildColumnMapping($excelHeaders, $expectedHeaders, $aliases);
        $mappedCount = count(array_filter($mapping, fn (int $i): bool => $i >= 0));
        $expectedNonEmpty = count(array_filter($expectedHeaders, fn (string $h): bool => trim($h) !== ''));
        $minMapped = max(6, (int) ceil($expectedNonEmpty * 0.12));
        if ($mappedCount < $minMapped) {
            $preview = $this->sampleNonEmptyHeadersForMessage($excelHeaders);
            throw new Exception(__('Excel columns could not be matched. Check report type, or ensure the header row contains column titles like “Tarih”, “Malzeme”, “Teslimat miktarı”. SAP exports often put headers on row 5–15 — this is now detected automatically. Detected headers (sample): :preview', [
                'preview' => $preview !== '' ? $preview : '—',
            ]));
        }

        $import->update([
            'meta' => array_merge($import->meta ?? [], [
                'delivery_excel_layout' => [
                    'excel_headers' => $excelHeaders,
                    'mapping_expected_to_excel' => $mapping,
                    'header_row_1based' => $headerRow1Based,
                ],
            ]),
        ]);

        $saved = 0;
        $errors = [];

        foreach ($excelRows as $index => $excelRow) {
            $excelRowNumber = $headerRow1Based + $index + 1;
            try {
                $rowData = $this->normalizeRowToExpectedColumns($excelRow, $mapping, count($expectedHeaders), $dateColumnExpectedIndices, $timeColumnExpectedIndices, $numericColumnExpectedIndices, $excelCalendar);
                DeliveryImportRow::query()->create([
                    'tenant_id' => $import->tenant_id ?? TenantContext::id(),
                    'delivery_import_id' => $import->id,
                    'row_index' => $excelRowNumber,
                    'row_data' => $rowData,
                ]);
                $saved++;
            } catch (\Throwable $e) {
                $errors[$excelRowNumber] = $e->getMessage();
            }
        }

        return [
            'total_rows' => count($excelRows),
            'saved' => $saved,
            'errors' => $errors,
        ];
    }

    /**
     * SAP / Excel çıktılarında başlık satırı genelde 1. satırda olmaz; tüm sayfalar ve ilk 40 satır taranır.
     *
     * @param  array<string, array<int, string>>  $aliases
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, excel_calendar: int|null, header_row_1based: int}
     */
    protected function readSpreadsheetWithHeaders(string $absolutePath, array $expectedHeaders, array $aliases = []): array
    {
        if (! class_exists(IOFactory::class)) {
            throw new Exception('PhpSpreadsheet gerekli.');
        }

        $spreadsheet = IOFactory::load($absolutePath);

        $bestScore = -1;
        $bestHeaderIdx = 0;
        /** @var array<int, array<int, mixed>>|null $bestRows */
        $bestRows = null;

        $sheetCount = $spreadsheet->getSheetCount();
        for ($si = 0; $si < $sheetCount; $si++) {
            $sheet = $spreadsheet->getSheet($si);
            $rows = $sheet->toArray();
            if ($rows === [] || $rows === [[]]) {
                continue;
            }
            $maxHeaderScan = min(40, count($rows));
            for ($h = 0; $h < $maxHeaderScan; $h++) {
                $headerCells = $rows[$h] ?? [];
                $score = $this->scoreHeaderRowAgainstExpected($headerCells, $expectedHeaders, $aliases);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestHeaderIdx = $h;
                    $bestRows = $rows;
                }
            }
        }

        if ($bestRows === null || $bestRows === []) {
            return ['headers' => [], 'rows' => [], 'excel_calendar' => null, 'header_row_1based' => 1];
        }

        $expectedNonEmptyCount = count(array_filter($expectedHeaders, fn (string $l): bool => trim($l) !== ''));
        $threshold = max(8, (int) ceil($expectedNonEmptyCount * 0.18));
        if ($bestScore < max(5, (int) ceil($threshold * 0.5))) {
            $candidateRow = $bestRows[$bestHeaderIdx] ?? [];
            $candidateHeaders = array_map(fn ($v) => $this->normalizeCellHeader($v), $candidateRow);
            $preview = $this->sampleNonEmptyHeadersForMessage($candidateHeaders);
            throw new Exception(__('No recognizable header row in the Excel file. Add a row with column titles (e.g. Tarih, Malzeme), or choose the correct report type. Best candidate row was :row; values (sample): :preview', [
                'row' => (string) ($bestHeaderIdx + 1),
                'preview' => $preview !== '' ? $preview : '—',
            ]));
        }

        $headerRow = $bestRows[$bestHeaderIdx] ?? [];
        $headers = array_map(fn ($v) => $this->normalizeCellHeader($v), $headerRow);
        $dataRows = array_slice($bestRows, $bestHeaderIdx + 1);
        $out = [];

        foreach ($dataRows as $row) {
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $padded = array_pad($row, count($headers), '');
            $out[] = array_map(function ($v) {
                if ($v === null || $v === '') {
                    return '';
                }
                if (is_numeric($v)) {
                    return $v;
                }

                return trim((string) $v);
            }, $padded);
        }

        $excelCalendar = null;
        if (method_exists($spreadsheet, 'getExcelCalendar')) {
            $cal = $spreadsheet->getExcelCalendar();
            if ($cal === Date::CALENDAR_WINDOWS_1900 || $cal === Date::CALENDAR_MAC_1904) {
                $excelCalendar = $cal;
            }
        }

        return [
            'headers' => $headers,
            'rows' => $out,
            'excel_calendar' => $excelCalendar,
            'header_row_1based' => $bestHeaderIdx + 1,
        ];
    }

    protected function normalizeCellHeader(mixed $value): string
    {
        $s = str_replace("\xC2\xA0", ' ', (string) $value);

        return trim($s);
    }

    /**
     * İçe aktarma hata mesajlarında Excel başlık önizlemesi.
     *
     * @param  array<int, string>  $headers
     */
    protected function sampleNonEmptyHeadersForMessage(array $headers, int $maxCells = 25): string
    {
        $parts = [];
        foreach ($headers as $h) {
            $t = trim($h);
            if ($t === '') {
                continue;
            }
            $parts[] = $t;
            if (count($parts) >= $maxCells) {
                break;
            }
        }

        return mb_substr(implode(', ', $parts), 0, 2000);
    }

    /**
     * buildColumnMapping ile uyumlu: beklenen başlık sırasına göre Excel satırında kaç sütun eşleşiyor.
     *
     * @param  array<int, mixed>  $headerRowCells
     * @param  array<int, string>  $expectedHeaders
     * @param  array<string, array<int, string>>  $aliases
     */
    protected function scoreHeaderRowAgainstExpected(array $headerRowCells, array $expectedHeaders, array $aliases = []): int
    {
        $normalize = fn (string $s): string => mb_strtolower(trim($s), 'UTF-8');
        $excelHeaders = array_map(fn ($v) => $this->normalizeCellHeader($v), $headerRowCells);
        $usedExcelIndices = [];
        $score = 0;

        foreach ($expectedHeaders as $expectedLabel) {
            $labelsToTry = array_merge([$expectedLabel], $aliases[$expectedLabel] ?? []);
            for ($j = 0; $j < count($excelHeaders); $j++) {
                if (in_array($j, $usedExcelIndices, true)) {
                    continue;
                }
                $excelNorm = $normalize($excelHeaders[$j]);
                foreach ($labelsToTry as $label) {
                    $ln = $normalize($this->normalizeCellHeader($label));
                    if ($ln === '') {
                        continue;
                    }
                    if ($excelNorm === $ln) {
                        $usedExcelIndices[] = $j;
                        $score++;
                        break 2;
                    }
                }
            }
        }

        return $score;
    }

    /**
     * Excel başlıklarını beklenen başlıklara eşler: expected_index => excel_column_index.
     * Tekrarlayan başlıklar soldan sağa sırayla eşleşir. Alias varsa önce ana başlık, sonra alias'lar denenir.
     *
     * @param  array<int, string>  $excelHeaders
     * @param  array<int, string>  $expectedHeaders
     * @param  array<string, array<int, string>>  $aliases  Başlık adı => Excel'de kabul edilen alternatif başlıklar
     * @return array<int, int>
     */
    public function buildColumnMapping(array $excelHeaders, array $expectedHeaders, array $aliases = []): array
    {
        $normalize = fn (string $s): string => mb_strtolower(trim($s), 'UTF-8');

        $mapping = [];
        $usedExcelIndices = [];

        foreach ($expectedHeaders as $expectedIndex => $expectedLabel) {
            $labelsToTry = array_merge([$expectedLabel], $aliases[$expectedLabel] ?? []);

            for ($j = 0; $j < count($excelHeaders); $j++) {
                if (in_array($j, $usedExcelIndices, true)) {
                    continue;
                }
                $excelNorm = $normalize($excelHeaders[$j]);
                foreach ($labelsToTry as $label) {
                    if ($excelNorm === $normalize($label)) {
                        $mapping[$expectedIndex] = $j;
                        $usedExcelIndices[] = $j;
                        break 2;
                    }
                }
            }

            if (! isset($mapping[$expectedIndex])) {
                $mapping[$expectedIndex] = -1;
            }
        }

        return $mapping;
    }

    /**
     * Rapor tipine göre başlık alias'larını döndürür (Excel'de farklı yazılan başlıkların eşlenmesi için).
     *
     * @return array<string, array<int, string>>
     */
    protected function getHeaderAliasesForImport(DeliveryImport $import): array
    {
        $types = config('delivery_report.report_types', []);
        if (! $import->report_type || ! isset($types[$import->report_type]['header_aliases'])) {
            return [];
        }

        return $types[$import->report_type]['header_aliases'];
    }

    /**
     * Rapor tipine göre tarih sütunu expected index'lerini döndürür (import'ta Excel seri → d.m.Y için).
     *
     * @return array<int, int>
     */
    protected function getDateColumnExpectedIndicesForImport(DeliveryImport $import): array
    {
        $types = config('delivery_report.report_types', []);
        if (! $import->report_type || ! isset($types[$import->report_type]['date_column_expected_indices'])) {
            return [];
        }

        return $types[$import->report_type]['date_column_expected_indices'];
    }

    /**
     * Rapor tipine göre saat sütunu expected index'lerini döndürür (import'ta Excel seri → g:i:s A için).
     *
     * @return array<int, int>
     */
    protected function getTimeColumnExpectedIndicesForImport(DeliveryImport $import): array
    {
        $types = config('delivery_report.report_types', []);
        if (! $import->report_type || ! isset($types[$import->report_type]['time_column_expected_indices'])) {
            return [];
        }

        return $types[$import->report_type]['time_column_expected_indices'];
    }

    /**
     * Rapor tipine göre sadece tarih (saat gösterme) kolon index'lerini döndürür (detay/export'ta d.m.Y).
     *
     * @return array<int, int>
     */
    protected function getDateOnlyColumnIndicesForImport(DeliveryImport $import): array
    {
        $types = config('delivery_report.report_types', []);
        if (! $import->report_type || ! isset($types[$import->report_type]['date_only_column_indices'])) {
            return [];
        }

        return $types[$import->report_type]['date_only_column_indices'];
    }

    /**
     * Tek bir satırın row_data'sını Teslimat Raporu Detayı listesindeki gibi formatlar.
     * Tarih/saat kolonları d.m.Y, g:i:s A vb.; diğer kolonlarda tarih benzeri string'ler dd.mm.yyyy'ye normalize edilir.
     *
     * @param  array<int, mixed>  $rowData
     * @return array<int, string>
     */
    public function formatRowDataForDisplay(DeliveryImport $import, array $rowData): array
    {
        $dateColumnIndices = $this->getDateColumnExpectedIndicesForImport($import);
        $timeColumnIndices = $this->getTimeColumnExpectedIndicesForImport($import);
        $dateOnlyColumnIndices = $this->getDateOnlyColumnIndicesForImport($import);

        $out = [];
        foreach ($rowData as $idx => $val) {
            if (in_array($idx, $dateColumnIndices, true) || in_array($idx, $timeColumnIndices, true)) {
                $out[$idx] = $this->formatDateForDisplay($val, $idx, $dateColumnIndices, $timeColumnIndices, $dateOnlyColumnIndices);
            } else {
                $v = $val;
                if ($v !== '' && $v !== null && preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/', trim((string) $v))) {
                    $v = $this->normalizeAnyDateToDmY($v);
                }
                $out[$idx] = $v === null || $v === '' ? '' : (string) $v;
            }
        }

        return $out;
    }

    /**
     * Tarih benzeri string'i dd.mm.yyyy formatına normalize eder (liste/export ile uyumlu).
     */
    protected function normalizeAnyDateToDmY(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $str = trim((string) $value);
        if ($str === '') {
            return '';
        }
        $datePart = preg_replace('/\s+.*$/', '', $str);
        if (! preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', $datePart)) {
            return $str;
        }
        $sep = strpos($datePart, '/') !== false ? '/' : '-';
        $parts = array_map('intval', explode($sep, $datePart));
        if (count($parts) !== 3 || $parts[2] < 1900 || $parts[2] > 2100) {
            return $str;
        }
        $a = $parts[0];
        $b = $parts[1];
        $y = $parts[2];
        if ($a > 12) {
            $d = $a;
            $m = $b;
        } elseif ($b > 12) {
            $m = $a;
            $d = $b;
        } else {
            $m = $a;
            $d = $b;
        }
        if ($m < 1 || $m > 12 || $d < 1 || $d > 31) {
            return $str;
        }

        return sprintf('%02d.%02d.%04d', $d, $m, $y);
    }

    /**
     * Tek bir hücre değerini detay sayfasındaki tarih/saat formatına çevirir.
     *
     * @param  array<int, int>  $dateColumnIndices
     * @param  array<int, int>  $timeColumnIndices
     * @param  array<int, int>  $dateOnlyColumnIndices
     */
    protected function formatDateForDisplay(
        mixed $value,
        int $colIndex,
        array $dateColumnIndices,
        array $timeColumnIndices,
        array $dateOnlyColumnIndices
    ): string {
        $isTime = in_array($colIndex, $timeColumnIndices, true);
        $isDate = in_array($colIndex, $dateColumnIndices, true);
        $dateOnly = in_array($colIndex, $dateOnlyColumnIndices, true);
        if (! $isTime && ! $isDate) {
            return $value === null || $value === '' ? '' : (string) $value;
        }
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value) && class_exists(Date::class)) {
            $num = (float) $value;
            $prev = Date::getExcelCalendar();
            $lastDt = null;
            $tz = new DateTimeZone('Europe/Istanbul');
            try {
                foreach ([Date::CALENDAR_WINDOWS_1900, Date::CALENDAR_MAC_1904] as $cal) {
                    Date::setExcelCalendar($cal);
                    $dt = Date::excelToDateTimeObject($num, $tz);
                    $lastDt = $dt;
                    $year = (int) $dt->format('Y');
                    if ($year >= 1990 && $year <= 2030) {
                        Date::setExcelCalendar($prev);
                        if ($isTime) {
                            return $dt->format('g:i:s A');
                        }
                        if ($dateOnly) {
                            return $dt->format('d.m.Y');
                        }
                        $hasTime = (int) $dt->format('His') !== 0;

                        return $hasTime ? $dt->format('d.m.Y g:i:s A') : $dt->format('d.m.Y');
                    }
                }
                Date::setExcelCalendar($prev);
                if ($lastDt !== null) {
                    if ($isTime) {
                        return $lastDt->format('g:i:s A');
                    }
                    if ($dateOnly) {
                        return $lastDt->format('d.m.Y');
                    }
                    $hasTime = (int) $lastDt->format('His') !== 0;

                    return $hasTime ? $lastDt->format('d.m.Y g:i:s A') : $lastDt->format('d.m.Y');
                }
            } catch (\Throwable $e) {
                Date::setExcelCalendar($prev);
            }
        }
        $str = trim((string) $value);
        $formats = ['j.n.Y H:i:s', 'j.n.Y H:i', 'j.n.Y g:i:s A', 'j.n.Y', 'd.m.Y', 'd.m.Y H:i', 'd.m.Y g:i:s A', 'Y-m-d', 'Y-m-d H:i:s', 'n/j/Y', 'm/d/Y', 'n-j-Y', 'm-d-Y'];
        foreach ($formats as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, $str);
                if ($parsed !== false) {
                    if ($isTime) {
                        return $parsed->format('g:i:s A');
                    }
                    if ($dateOnly) {
                        return $parsed->format('d.m.Y');
                    }
                    $hasTime = $parsed->format('His') !== '000000';

                    return $hasTime ? $parsed->format('d.m.Y g:i:s A') : $parsed->format('d.m.Y');
                }
            } catch (InvalidFormatException $e) {
                continue;
            } catch (\Throwable $e) {
                continue;
            }
        }
        $value = $this->normalizeDateTimeStringForParse($value);
        try {
            $parsed = Carbon::parse($value);
            if ($isTime) {
                return $parsed->format('g:i:s A');
            }
            if ($dateOnly) {
                return $parsed->format('d.m.Y');
            }
            $hasTime = $parsed->format('His') !== '000000';

            return $hasTime ? $parsed->format('d.m.Y g:i:s A') : $parsed->format('d.m.Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * ISO benzeri tarih string'lerinde boşlukla ayrılmış timezone'u (+ ile) Carbon'ın parse edebilmesi için düzeltir.
     * Örn: "2026-01-26T00:00:00 03:00" -> "2026-01-26T00:00:00+03:00"
     */
    protected function normalizeDateTimeStringForParse(mixed $value): mixed
    {
        $str = is_string($value) ? trim($value) : (string) $value;
        if ($str === '') {
            return $value;
        }
        $normalized = preg_replace('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\s+(\d{2}:?\d{2})\s*$/u', '$1+$2', $str);

        return $normalized !== null ? $normalized : $value;
    }

    /**
     * Rapor tipine göre sayısal kolon expected index'lerini döndürür (TR 1.234,56 → 1234.56 saklanır).
     *
     * @return array<int, int>
     */
    protected function getNumericColumnExpectedIndicesForImport(DeliveryImport $import): array
    {
        $types = config('delivery_report.report_types', []);
        if (! $import->report_type || ! isset($types[$import->report_type]['numeric_column_expected_indices'])) {
            return [];
        }

        return $types[$import->report_type]['numeric_column_expected_indices'];
    }

    /**
     * Tek bir Excel satırını beklenen kolon sırasına göre normalize eder.
     * Tarih → d.m.Y, Saat → g:i:s A, Sayı → nokta ondalık string, Diğer → trim.
     *
     * @param  array<int, int>  $timeColumnExpectedIndices  Saat (g:i:s A)
     * @param  array<int, int>  $numericColumnExpectedIndices  Sayısal (TR → nokta ondalık)
     * @return array<int, string>
     */
    public function normalizeRowToExpectedColumns(array $excelRow, array $mapping, int $expectedCount, array $dateColumnExpectedIndices = [], array $timeColumnExpectedIndices = [], array $numericColumnExpectedIndices = [], ?int $excelCalendar = null): array
    {
        $rowData = [];
        $calendar = $excelCalendar ?? Date::CALENDAR_WINDOWS_1900;

        for ($i = 0; $i < $expectedCount; $i++) {
            $excelCol = $mapping[$i] ?? -1;
            $raw = ($excelCol >= 0 && array_key_exists($excelCol, $excelRow)) ? $excelRow[$excelCol] : null;

            if (in_array($i, $timeColumnExpectedIndices, true) && $raw !== null && $raw !== '') {
                $value = $this->formatExcelTimeForStorage($raw, $calendar);
            } elseif (in_array($i, $dateColumnExpectedIndices, true) && $raw !== null && $raw !== '') {
                $value = $this->formatExcelDateForStorage($raw, $calendar);
            } elseif (in_array($i, $numericColumnExpectedIndices, true)) {
                $value = $this->normalizeNumericForStorage($raw);
            } else {
                $value = $this->normalizeTextForStorage($raw);
            }

            $rowData[] = $value;
        }

        return $rowData;
    }

    /**
     * Metin/boş değeri normalize eder: trim, boş/null → ''.
     */
    protected function normalizeTextForStorage(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        return trim((string) $raw);
    }

    /**
     * Sayısal değeri normalize eder: TR (1.234,56) → nokta ondalık (1234.56) string.
     */
    protected function normalizeNumericForStorage(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }
        if (is_numeric($raw)) {
            return (string) (is_float($raw) || is_int($raw) ? $raw : (float) $raw);
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return '';
        }
        $tr = preg_replace('/\s+/', '', $s);
        if (preg_match('/^[\d.,\-]+$/', $tr)) {
            if (str_contains($tr, ',')) {
                $tr = str_replace('.', '', $tr);
                $tr = str_replace(',', '.', $tr);
            }

            return is_numeric($tr) ? (string) (float) $tr : $s;
        }

        return $s;
    }

    /**
     * Excel tarih/saat değerini takvime göre d.m.Y veya d.m.Y 9:02:54 AM (g:i:s A) string'e çevirir.
     */
    protected function formatExcelDateForStorage(mixed $value, int $excelCalendar): string
    {
        $numericValue = null;
        if (is_numeric($value)) {
            $numericValue = (float) $value;
        } elseif (is_string($value)) {
            $candidate = str_replace(',', '.', trim($value));
            if (is_numeric($candidate)) {
                $numericValue = (float) $candidate;
            }
        }

        if ($numericValue !== null && $numericValue >= 1000 && $numericValue < 2958466 && class_exists(Date::class)) {
            $dt = $this->excelSerialToDateTime($numericValue, $excelCalendar);
            if ($dt !== null) {
                $hasTime = (int) $dt->format('His') !== 0;

                return $hasTime ? $dt->format('d.m.Y g:i:s A') : $dt->format('d.m.Y');
            }
        }

        $str = trim((string) $value);
        $formats = ['j.n.Y H:i:s', 'j.n.Y H:i', 'j.n.Y g:i:s A', 'j.n.Y', 'd.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y g:i:s A', 'd.m.Y', 'Y-m-d H:i:s', 'Y-m-d'];
        if (str_contains($str, '/')) {
            $formatsSlashFirst = ['n/j/Y', 'm/d/Y', 'n/j/Y H:i:s', 'm/d/Y H:i:s', 'j/n/Y', 'd/m/Y'];
            foreach ($formatsSlashFirst as $fmt) {
                $dt = @DateTime::createFromFormat($fmt, $str);
                if ($dt !== false) {
                    $hasTime = (int) $dt->format('His') !== 0;

                    return $hasTime ? $dt->format('d.m.Y g:i:s A') : $dt->format('d.m.Y');
                }
            }
        }
        foreach ($formats as $fmt) {
            $dt = @DateTime::createFromFormat($fmt, $str);
            if ($dt !== false) {
                $hasTime = (int) $dt->format('His') !== 0;

                return $hasTime ? $dt->format('d.m.Y g:i:s A') : $dt->format('d.m.Y');
            }
        }

        return $str;
    }

    /**
     * Excel saat değerini (seri veya string) 9:02:54 AM (g:i:s A) string'e çevirir.
     */
    protected function formatExcelTimeForStorage(mixed $value, int $excelCalendar): string
    {
        $numericValue = null;
        if (is_numeric($value)) {
            $numericValue = (float) $value;
        } elseif (is_string($value)) {
            $candidate = str_replace(',', '.', trim($value));
            if (is_numeric($candidate)) {
                $numericValue = (float) $candidate;
            }
        }

        if ($numericValue !== null && class_exists(Date::class)) {
            $dt = $this->excelSerialToDateTime($numericValue, $excelCalendar);
            if ($dt !== null) {
                return $dt->format('g:i:s A');
            }
        }

        return trim((string) $value);
    }

    /**
     * Excel seri numarasını DateTime'a çevirir; takvim yanlışsa 1904 dener.
     * Türkiye saat dilimi (Europe/Istanbul) kullanılır; tarih kayması önlenir.
     */
    private function excelSerialToDateTime(float $numericValue, int $excelCalendar): ?DateTimeInterface
    {
        $timezone = new DateTimeZone('Europe/Istanbul');
        $prev = Date::getExcelCalendar();
        Date::setExcelCalendar($excelCalendar);
        try {
            $dt = Date::excelToDateTimeObject($numericValue, $timezone);
            $year = (int) $dt->format('Y');
            $nowYear = (int) date('Y');
            if ($year > $nowYear + 1 || $year < $nowYear - 2) {
                Date::setExcelCalendar(Date::CALENDAR_MAC_1904);
                $dt = Date::excelToDateTimeObject($numericValue, $timezone);
            }

            return $dt;
        } catch (\Throwable) {
            return null;
        } finally {
            Date::setExcelCalendar($prev);
        }
    }

    /**
     * Batch'in rapor tipine göre beklenen başlıkları döndürür.
     *
     * @return array<int, string>
     */
    public function getExpectedHeadersForImport(DeliveryImport $import): array
    {
        $types = config('delivery_report.report_types', []);
        if ($import->report_type && isset($types[$import->report_type]['headers'])) {
            return $types[$import->report_type]['headers'];
        }

        return config('delivery_report.expected_headers', []);
    }

    /**
     * Detay tablosunda Excel’deki sol→sağ sütun sırasını göstermek için: ham başlık + eşlenen rapor alanı indeksi.
     *
     * @return array<int, array{excel_col: int, header: string, expected_index: int|null}>
     */
    public function getExcelColumnLayoutForDisplay(DeliveryImport $import): array
    {
        $layout = data_get($import->meta, 'delivery_excel_layout');
        if (! is_array($layout) || ! isset($layout['excel_headers']) || ! is_array($layout['excel_headers'])) {
            return [];
        }

        /** @var array<int, string> $headers */
        $headers = $layout['excel_headers'];
        $mapping = $layout['mapping_expected_to_excel'] ?? [];
        if (! is_array($mapping)) {
            $mapping = [];
        }

        $excelToExpected = [];
        foreach ($mapping as $expectedIndex => $excelIndex) {
            $ei = (int) $expectedIndex;
            $ej = (int) $excelIndex;
            if ($ej >= 0) {
                $excelToExpected[$ej] = $ei;
            }
        }

        $out = [];
        foreach ($headers as $j => $headerText) {
            $j = (int) $j;
            $out[] = [
                'excel_col' => $j,
                'header' => is_string($headerText) ? $headerText : (string) $headerText,
                'expected_index' => $excelToExpected[$j] ?? null,
            ];
        }

        return $out;
    }
}
