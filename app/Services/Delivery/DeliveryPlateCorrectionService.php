<?php

namespace App\Services\Delivery;

use App\Models\DeliveryImport;
use App\Models\DeliveryImportPlateCorrection;
use App\Models\DeliveryImportRow;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DeliveryPlateCorrectionService
{
    public function createRequest(
        DeliveryImport $import,
        DeliveryImportRow $row,
        string $newPlate,
        ?string $reason,
        ?int $requestedBy
    ): DeliveryImportPlateCorrection {
        $plateIndex = $this->resolvePlateExpectedIndex($import);
        $currentPlate = trim((string) (($row->row_data ?? [])[$plateIndex] ?? ''));

        if ($currentPlate === '') {
            throw new Exception('Satırda plaka bulunamadı.');
        }

        $newPlate = trim($newPlate);
        if ($newPlate === '') {
            throw new Exception('Yeni plaka boş olamaz.');
        }

        if ($this->normalizePlate($newPlate) === $this->normalizePlate($currentPlate)) {
            throw new Exception('Yeni plaka mevcut plaka ile aynı.');
        }

        return DeliveryImportPlateCorrection::query()->create([
            'tenant_id' => $import->tenant_id,
            'delivery_import_id' => $import->id,
            'delivery_import_row_id' => $row->id,
            'row_index' => (int) $row->row_index,
            'old_plate' => $currentPlate,
            'new_plate' => $newPlate,
            'status' => 'pending',
            'request_reason' => $reason !== null ? trim($reason) : null,
            'requested_by' => $requestedBy,
        ]);
    }

    public function approveRequest(DeliveryImportPlateCorrection $request, ?int $reviewedBy, ?string $reviewNote = null): void
    {
        if ($request->status !== 'pending') {
            throw new Exception('Sadece bekleyen talepler onaylanabilir.');
        }

        DB::transaction(function () use ($request, $reviewedBy, $reviewNote): void {
            $request->refresh();

            /** @var DeliveryImportRow $row */
            $row = DeliveryImportRow::query()->findOrFail($request->delivery_import_row_id);
            /** @var DeliveryImport $import */
            $import = DeliveryImport::query()->findOrFail($request->delivery_import_id);

            $plateIndex = $this->resolvePlateExpectedIndex($import);
            $rowData = $row->row_data ?? [];
            $rowData[$plateIndex] = $request->new_plate;

            $row->update([
                'row_data' => $rowData,
            ]);

            $this->applyPlateChangeToStoredExcel($import, (int) $row->row_index, $request->new_plate, $plateIndex);

            $request->update([
                'status' => 'approved',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'applied_at' => now(),
                'review_note' => $reviewNote !== null ? trim($reviewNote) : null,
            ]);
        });
    }

    public function rejectRequest(DeliveryImportPlateCorrection $request, ?int $reviewedBy, ?string $reviewNote = null): void
    {
        if ($request->status !== 'pending') {
            throw new Exception('Sadece bekleyen talepler reddedilebilir.');
        }

        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'review_note' => $reviewNote !== null ? trim($reviewNote) : null,
        ]);
    }

    protected function resolvePlateExpectedIndex(DeliveryImport $import): int
    {
        $types = config('delivery_report.report_types', []);
        $reportConfig = $import->report_type ? ($types[$import->report_type] ?? []) : [];
        $vehicleMatching = $reportConfig['material_pivot']['vehicle_matching'] ?? null;
        if (is_array($vehicleMatching) && isset($vehicleMatching['plate_index'])) {
            return (int) $vehicleMatching['plate_index'];
        }
        $dimensions = $reportConfig['pivot_dimensions'] ?? [];
        $idx = array_search('Plaka', $dimensions, true);
        if ($idx === false) {
            throw new Exception('Plaka sütunu rapor konfigürasyonunda bulunamadı.');
        }

        return (int) $idx;
    }

    protected function applyPlateChangeToStoredExcel(DeliveryImport $import, int $rowIndex, string $newPlate, int $plateExpectedIndex): void
    {
        if (! $import->file_path) {
            return;
        }

        $abs = Storage::disk('local')->path($import->file_path);
        if (! file_exists($abs)) {
            return;
        }

        $mapping = data_get($import->meta, 'delivery_excel_layout.mapping_expected_to_excel');
        if (! is_array($mapping) || ! array_key_exists($plateExpectedIndex, $mapping)) {
            return;
        }

        $excelCol = (int) $mapping[$plateExpectedIndex];
        if ($excelCol < 0) {
            return;
        }

        $spreadsheet = IOFactory::load($abs);
        $sheet = $spreadsheet->getSheet(0);
        $sheet->setCellValue([$excelCol + 1, $rowIndex], $newPlate);

        $type = IOFactory::identify($abs);
        $writer = IOFactory::createWriter($spreadsheet, $type);
        $writer->save($abs);
    }

    protected function normalizePlate(string $plate): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($plate)));
    }
}
