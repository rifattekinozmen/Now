<?php

namespace App\Exports;

use App\Models\DeliveryImport;
use App\Models\DeliveryImportRow;
use App\Services\Delivery\DeliveryReportImportService;
use App\Services\Delivery\DeliveryReportPivotService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class DeliveryImportAnalysisExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private readonly DeliveryImport $deliveryImport,
        private readonly DeliveryReportPivotService $pivotService,
        private readonly DeliveryReportImportService $importService,
        private readonly ?string $selectedPlate = null
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        $plateIndex = $this->resolvePlateIndex();
        $allPivot = $this->pivotService->buildMaterialPivot($this->deliveryImport);
        $vehicleReport = $this->pivotService->buildVehicleDdBdReport($this->deliveryImport);
        $selectedPlate = $this->selectedPlate !== null ? trim($this->selectedPlate) : null;
        $invoiceHeader = $this->invoiceHeader();

        if ($plateIndex === null) {
            $sheets[] = new DeliveryImportSummarySheet($this->summarySheetName(), $this->deliveryImport, $allPivot, $vehicleReport, []);
            $sheets[] = new DeliveryImportTableSheet($this->masterPivotSheetName(), $this->pivotHeader($allPivot), $this->pivotRows($allPivot));
            $sheets[] = new DeliveryImportTableSheet(
                $this->masterInvoiceSheetName(),
                $invoiceHeader,
                $this->invoiceRows($allPivot)
            );

            return $sheets;
        }

        $plates = $this->collectPlates($plateIndex);
        if ($selectedPlate !== null && $selectedPlate !== '') {
            $matchedPlate = $this->findMatchedPlate($plates, $selectedPlate);
            if ($matchedPlate !== null) {
                $platePivot = $this->pivotService->buildMaterialPivot($this->deliveryImport, $matchedPlate, $plateIndex);
                $filteredVehicleReport = array_values(array_filter(
                    $vehicleReport,
                    fn (array $item): bool => $this->normalizePlate((string) ($item['plaka'] ?? '')) === $this->normalizePlate($matchedPlate)
                ));
                $sheets[] = new DeliveryImportSummarySheet($this->summarySheetName(), $this->deliveryImport, $platePivot, $filteredVehicleReport, [$matchedPlate]);
                $sheets[] = new DeliveryImportTableSheet($this->platePivotSheetName(), $this->pivotHeader($platePivot), $this->pivotRows($platePivot));
                $sheets[] = new DeliveryImportTableSheet(
                    $this->plateInvoiceSheetName(),
                    $invoiceHeader,
                    $this->invoiceRows($platePivot)
                );
                $sheets[] = new DeliveryImportPlateDetailSheet(
                    $this->safeSheetTitle($this->plateDetailPrefix().$this->sanitizeSheetSuffix($matchedPlate)),
                    $matchedPlate,
                    $this->pivotHeader($platePivot),
                    $this->pivotRows($platePivot),
                    $invoiceHeader,
                    $this->invoiceRows($platePivot),
                    $this->importedRowsHeader(),
                    $this->importedRows($matchedPlate, $plateIndex)
                );

                return $sheets;
            }
        }

        $sheets[] = new DeliveryImportSummarySheet(
            $this->summarySheetName(),
            $this->deliveryImport,
            $allPivot,
            $vehicleReport,
            $plates
        );
        $sheets[] = new DeliveryImportTableSheet(
            $this->masterPivotSheetName(),
            $this->pivotHeader($allPivot),
            $this->pivotRows($allPivot)
        );
        $sheets[] = new DeliveryImportTableSheet(
            $this->masterInvoiceSheetName(),
            $invoiceHeader,
            $this->invoiceRows($allPivot)
        );

        foreach ($plates as $plate) {
            $platePivot = $this->pivotService->buildMaterialPivot($this->deliveryImport, $plate, $plateIndex);
            $sheets[] = new DeliveryImportPlateDetailSheet(
                $this->safeSheetTitle($this->plateDetailPrefix().$this->sanitizeSheetSuffix($plate)),
                $plate,
                $this->pivotHeader($platePivot),
                $this->pivotRows($platePivot),
                $invoiceHeader,
                $this->invoiceRows($platePivot),
                $this->importedRowsHeader(),
                $this->importedRows($plate, $plateIndex)
            );
        }

        return $sheets;
    }

    private function findMatchedPlate(array $plates, string $selectedPlate): ?string
    {
        $target = $this->normalizePlate($selectedPlate);
        foreach ($plates as $plate) {
            if ($this->normalizePlate($plate) === $target) {
                return $plate;
            }
        }

        return null;
    }

    private function resolvePlateIndex(): ?int
    {
        $types = config('delivery_report.report_types', []);
        $rt = $this->deliveryImport->report_type;
        $reportConfig = $rt ? ($types[$rt] ?? []) : [];
        $vehicleMatching = $reportConfig['material_pivot']['vehicle_matching'] ?? null;

        if (is_array($vehicleMatching) && isset($vehicleMatching['plate_index'])) {
            return (int) $vehicleMatching['plate_index'];
        }

        $dimensions = $reportConfig['pivot_dimensions'] ?? [];
        $plakaIndex = array_search('Plaka', $dimensions, true);

        return $plakaIndex === false ? null : (int) $plakaIndex;
    }

    private function collectPlates(int $plateIndex): array
    {
        $rows = $this->deliveryImport->reportRows()->orderBy('row_index')->get(['row_data']);
        $set = [];
        foreach ($rows as $row) {
            $plate = trim((string) (($row->row_data ?? [])[$plateIndex] ?? ''));
            if ($plate !== '') {
                $set[$this->normalizePlate($plate)] = $plate;
            }
        }

        $plates = array_values($set);
        usort($plates, fn (string $a, string $b): int => strcmp($this->normalizePlate($a), $this->normalizePlate($b)));

        return $plates;
    }

    private function normalizePlate(string $plate): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($plate)));
    }

    private function pivotHeader(array $pivot): array
    {
        $header = [__('Date')];
        foreach (($pivot['materials'] ?? []) as $m) {
            $header[] = (string) ($m['label'] ?? '');
        }
        $header[] = __('Total Ton');
        $header[] = __('Total Count');
        $header[] = __('Empty-Full Valid');
        $header[] = __('Full-Full Valid');
        $header[] = __('Material Short Text');

        return $header;
    }

    private function invoiceHeader(): array
    {
        return [
            __('Route'),
            __('Material Code'),
            __('Material'),
            __('From-To'),
            __('Transport Type'),
            __('Quantity (Ton)'),
        ];
    }

    private function pivotRows(array $pivot): array
    {
        $materials = $pivot['materials'] ?? [];
        $rows = $pivot['rows'] ?? [];
        $totals = $pivot['totals_row'] ?? [];
        $lines = [];

        foreach ($rows as $r) {
            $line = [(string) ($r['tarih'] ?? '')];
            foreach ($materials as $m) {
                $key = (string) ($m['key'] ?? '');
                $qty = (float) ($r['material_totals'][$key] ?? 0);
                $cnt = (int) ($r['material_counts'][$key] ?? 0);
                $line[] = number_format($qty, 2, ',', '.').' Ton / '.$cnt.' Adet';
            }
            $line[] = number_format((float) ($r['row_total'] ?? 0), 2, ',', '.');
            $line[] = (string) ($r['row_total_count'] ?? 0);
            $line[] = number_format((float) ($r['boş_dolu'] ?? 0), 2, ',', '.');
            $line[] = number_format((float) ($r['dolu_dolu'] ?? 0), 2, ',', '.');
            $line[] = (string) ($r['malzeme_kisa_metni'] ?? '');
            $lines[] = $line;
        }

        if ($rows !== []) {
            $totalLine = ['Toplam'];
            foreach ($materials as $m) {
                $key = (string) ($m['key'] ?? '');
                $qty = (float) ($totals['material_totals'][$key] ?? 0);
                $cnt = (int) ($totals['material_counts'][$key] ?? 0);
                $totalLine[] = number_format($qty, 2, ',', '.').' Ton / '.$cnt.' Adet';
            }
            $totalLine[] = number_format((float) ($totals['row_total'] ?? 0), 2, ',', '.');
            $totalLine[] = (string) ($totals['row_total_count'] ?? 0);
            $totalLine[] = number_format((float) ($totals['boş_dolu'] ?? 0), 2, ',', '.');
            $totalLine[] = number_format((float) ($totals['dolu_dolu'] ?? 0), 2, ',', '.');
            $totalLine[] = '';
            $lines[] = $totalLine;
        }

        return $lines;
    }

    private function invoiceRows(array $pivot): array
    {
        $lines = [];
        foreach (($pivot['fatura_rota_gruplari'] ?? []) as $group) {
            $route = (string) ($group['route_label'] ?? '');
            foreach (($group['kalemler'] ?? []) as $item) {
                $lines[] = [
                    $route,
                    (string) ($item['material_code'] ?? ''),
                    (string) ($item['material_short'] ?? ''),
                    (string) ($item['nerden_nereye'] ?? ''),
                    (string) ($item['tasima_tipi'] ?? ''),
                    isset($item['miktar']) ? number_format((float) $item['miktar'], 2, ',', '.') : '',
                ];
            }
        }

        return $lines;
    }

    private function importedRowsHeader(): array
    {
        $headers = [__('Row Number')];
        foreach ($this->importService->getExpectedHeadersForImport($this->deliveryImport) as $header) {
            $headers[] = (string) $header;
        }

        return $headers;
    }

    private function importedRows(string $plate, int $plateIndex): array
    {
        $rows = DeliveryImportRow::query()
            ->where('delivery_import_id', $this->deliveryImport->id)
            ->orderBy('row_index')
            ->get(['row_index', 'row_data']);

        $lines = [];
        foreach ($rows as $row) {
            $rowPlate = trim((string) (($row->row_data ?? [])[$plateIndex] ?? ''));
            if ($this->normalizePlate($rowPlate) !== $this->normalizePlate($plate)) {
                continue;
            }
            $display = $this->importService->formatRowDataForDisplay($this->deliveryImport, $row->row_data ?? []);
            $lines[] = array_merge([(string) $row->row_index], array_map(
                static fn ($value): string => is_scalar($value) ? (string) $value : '',
                $display
            ));
        }

        return $lines;
    }

    private function sanitizeSheetSuffix(string $plate): string
    {
        $clean = preg_replace('/[\\\\\\/?*\\[\\]:]/', '-', $plate);
        $clean = trim((string) $clean);

        return $clean !== '' ? $clean : 'Plaka';
    }

    private function safeSheetTitle(string $title): string
    {
        return mb_substr($title, 0, 31);
    }

    private function isTurkish(): bool
    {
        return str_starts_with(app()->getLocale(), 'tr');
    }

    private function summarySheetName(): string
    {
        return $this->isTurkish() ? '01_YoneticiOzeti' : '01_ExecutiveSummary';
    }

    private function masterPivotSheetName(): string
    {
        return $this->isTurkish() ? '02_GenelPivot' : '02_MasterPivot';
    }

    private function masterInvoiceSheetName(): string
    {
        return $this->isTurkish() ? '03_FaturaKalemleri' : '03_InvoiceLines';
    }

    private function platePivotSheetName(): string
    {
        return $this->isTurkish() ? '02_PlakaPivotOzeti' : '02_PlatePivotSummary';
    }

    private function plateInvoiceSheetName(): string
    {
        return $this->isTurkish() ? '03_PlakaFaturaKalemleri' : '03_PlateInvoiceLines';
    }

    private function plateDetailPrefix(): string
    {
        return $this->isTurkish() ? '04_PlakaDetay_' : '04_PlateDetail_';
    }
}

final class DeliveryImportTableSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    public function __construct(
        private readonly string $title,
        private readonly array $header,
        private readonly array $rows
    ) {}

    public function array(): array
    {
        return [$this->header, ...$this->rows];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->header));
        $lastRow = max(1, count($this->rows) + 1);

        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getRowDimension(1)->setRowHeight(24);

        foreach (range(1, count($this->header)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->header));
                $lastRow = max(1, count($this->rows) + 1);
                $event->sheet->freezePane('A2');
                $event->sheet->setAutoFilter('A1:'.$lastColumn.$lastRow);
            },
        ];
    }

    public function title(): string
    {
        return $this->title;
    }
}

final class DeliveryImportSummarySheet implements FromArray, WithTitle, WithStyles
{
    public function __construct(
        private readonly string $title,
        private readonly DeliveryImport $deliveryImport,
        private readonly array $allPivot,
        private readonly array $vehicleReport,
        private readonly array $plates
    ) {}

    public function array(): array
    {
        $totalTon = (float) ($this->allPivot['totals_row']['row_total'] ?? 0);
        $totalRows = (int) ($this->deliveryImport->row_count ?? 0);
        $plateCount = count($this->plates);
        $topPlates = $this->topPlates($this->vehicleReport);

        $rows = [
            [__('7-Day Delivery Analysis Summary'), ''],
            [__('Import No'), (string) ($this->deliveryImport->reference_no ?? $this->deliveryImport->id)],
            [__('Total Ton'), number_format($totalTon, 2, ',', '.')],
            [__('Total Rows'), (string) $totalRows],
            [__('Plate Count'), (string) $plateCount],
            ['', ''],
            [__('Usage Note'), __('Use the summary, pivot, invoice, and plate detail sheets for full analysis.')],
            ['', ''],
            [__('Top 10 Plates by Tonnage'), __('Total Ton')],
        ];

        foreach ($topPlates as $plate) {
            $rows[] = [$plate['plaka'], number_format((float) $plate['toplam_miktar'], 2, ',', '.')];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = 9 + min(10, count($this->vehicleReport));

        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A9:B9')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
        ]);
        $sheet->getStyle('A1:B'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
        $sheet->getStyle('A1:B'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:B'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(9)->setRowHeight(24);
        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(36);

        return [];
    }

    public function title(): string
    {
        return $this->title;
    }

    private function topPlates(array $vehicleReport): array
    {
        usort($vehicleReport, fn (array $a, array $b): int => ((float) ($b['toplam_miktar'] ?? 0)) <=> ((float) ($a['toplam_miktar'] ?? 0)));

        return array_slice($vehicleReport, 0, 10);
    }
}

final class DeliveryImportPlateDetailSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    public function __construct(
        private readonly string $title,
        private readonly string $plate,
        private readonly array $pivotHeader,
        private readonly array $pivotRows,
        private readonly array $invoiceHeader,
        private readonly array $invoiceRows,
        private readonly array $importedHeader,
        private readonly array $importedRows
    ) {}

    public function array(): array
    {
        $rows = [
            [__('Plate Detail'), $this->plate],
            ['', ''],
            [__('Pivot Table')],
        ];
        $rows = [...$rows, ...$this->table($this->pivotHeader, $this->pivotRows), ['', ''], [__('Invoice Lines')]];
        $rows = [...$rows, ...$this->table($this->invoiceHeader, $this->invoiceRows), ['', ''], [__('Imported Rows')]];

        return [...$rows, ...$this->table($this->importedHeader, $this->importedRows)];
    }

    public function styles(Worksheet $sheet): array
    {
        $pivotHeaderRow = 4;
        $pivotLastRow = $pivotHeaderRow + count($this->pivotRows);
        $invoiceTitleRow = $pivotLastRow + 2;
        $invoiceHeaderRow = $invoiceTitleRow + 1;
        $invoiceLastRow = $invoiceHeaderRow + count($this->invoiceRows);
        $importedTitleRow = $invoiceLastRow + 2;
        $importedHeaderRow = $importedTitleRow + 1;
        $importedLastRow = $importedHeaderRow + count($this->importedRows);
        $maxColumns = max(count($this->pivotHeader), count($this->invoiceHeader), count($this->importedHeader), 2);
        $lastColumn = Coordinate::stringFromColumnIndex($maxColumns);

        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        foreach (['A3', 'A'.$invoiceTitleRow, 'A'.$importedTitleRow] as $sectionCell) {
            $sheet->getStyle($sectionCell.':'.$lastColumn.$sectionCell)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '111827']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            ]);
        }
        foreach ([$pivotHeaderRow, $invoiceHeaderRow, $importedHeaderRow] as $headerRow) {
            $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
        $sheet->getStyle('A1:'.$lastColumn.$importedLastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle('A1:'.$lastColumn.$importedLastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:'.$lastColumn.$importedLastRow)->getAlignment()->setWrapText(true);
        $sheet->getDefaultRowDimension()->setRowHeight(19);
        $sheet->getRowDimension(1)->setRowHeight(26);
        foreach ([$pivotHeaderRow, $invoiceHeaderRow, $importedHeaderRow] as $headerRow) {
            $sheet->getRowDimension($headerRow)->setRowHeight(23);
        }

        foreach (range(1, max(20, $maxColumns)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        return [];
    }

    public function title(): string
    {
        return $this->title;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $pivotHeaderRow = 4;
                $pivotLastRow = $pivotHeaderRow + count($this->pivotRows);
                $invoiceTitleRow = $pivotLastRow + 2;
                $invoiceHeaderRow = $invoiceTitleRow + 1;
                $invoiceLastRow = $invoiceHeaderRow + count($this->invoiceRows);
                $importedTitleRow = $invoiceLastRow + 2;
                $importedHeaderRow = $importedTitleRow + 1;
                $importedLastRow = $importedHeaderRow + count($this->importedRows);

                $maxColumns = max(count($this->pivotHeader), count($this->invoiceHeader), count($this->importedHeader), 2);
                $lastColumn = Coordinate::stringFromColumnIndex($maxColumns);

                $event->sheet->setAutoFilter('A'.$pivotHeaderRow.':'.$lastColumn.$importedLastRow);
                $event->sheet->freezePane('A5');
            },
        ];
    }

    private function table(array $header, array $rows): array
    {
        return [$header, ...$rows];
    }
}
