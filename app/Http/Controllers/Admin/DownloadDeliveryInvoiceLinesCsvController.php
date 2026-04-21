<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryImport;
use App\Services\Delivery\DeliveryReportPivotService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDeliveryInvoiceLinesCsvController extends Controller
{
    public function __invoke(DeliveryImport $deliveryImport, DeliveryReportPivotService $pivotService): StreamedResponse
    {
        Gate::authorize('view', $deliveryImport);

        $pivot = $pivotService->buildMaterialPivot($deliveryImport);
        $filename = 'teslimat-fatura-kalemleri-'.$deliveryImport->id.'.csv';

        return response()->streamDownload(function () use ($pivot): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $header = ['rota', 'malzeme_kodu', 'malzeme', 'nerden_nereye', 'tasima_tipi', 'miktar_ton'];
            fputcsv($out, $header, ';');

            $groups = $pivot['fatura_rota_gruplari'] ?? [];
            foreach ($groups as $grup) {
                $rota = $grup['route_label'] ?? '';
                foreach ($grup['kalemler'] ?? [] as $k) {
                    fputcsv($out, [
                        $rota,
                        $k['material_code'] ?? '',
                        $k['material_short'] ?? '',
                        $k['nerden_nereye'] ?? '',
                        $k['tasima_tipi'] ?? '',
                        isset($k['miktar']) ? number_format((float) $k['miktar'], 2, ',', '.') : '',
                    ], ';');
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
