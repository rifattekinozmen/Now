<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DeliveryImportAnalysisExport;
use App\Http\Controllers\Controller;
use App\Models\DeliveryImport;
use App\Services\Delivery\DeliveryReportImportService;
use App\Services\Delivery\DeliveryReportPivotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadDeliveryAnalysisExcelController extends Controller
{
    public function __invoke(
        Request $request,
        DeliveryImport $deliveryImport,
        DeliveryReportPivotService $pivotService,
        DeliveryReportImportService $importService
    ): BinaryFileResponse {
        Gate::authorize('view', $deliveryImport);
        $selectedPlate = trim((string) $request->query('plate', ''));
        $datePart = optional($deliveryImport->import_date)->format('Ymd') ?: now()->format('Ymd');
        $referencePart = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($deliveryImport->reference_no ?? 'IMPORT-'.$deliveryImport->id));
        $referencePart = trim((string) $referencePart, '-');
        $referencePart = $referencePart !== '' ? $referencePart : 'IMPORT-'.$deliveryImport->id;
        $platePart = $selectedPlate !== '' ? '-'.__('Plate').'-'.preg_replace('/[^A-Za-z0-9]/', '', strtoupper($selectedPlate)) : '';
        $baseName = __('Delivery-Analysis-Report');
        $safeBaseName = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $baseName);
        $safeBaseName = trim((string) $safeBaseName, '-');
        $safeBaseName = $safeBaseName !== '' ? $safeBaseName : 'Delivery-Analysis-Report';
        $filename = $safeBaseName.'-'.$datePart.'-'.$referencePart.$platePart.'.xlsx';

        return Excel::download(
            new DeliveryImportAnalysisExport($deliveryImport, $pivotService, $importService, $selectedPlate !== '' ? $selectedPlate : null),
            $filename
        );
    }
}
