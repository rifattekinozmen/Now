<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryImport;
use App\Services\Delivery\DeliveryReportPivotService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDeliveryMaterialPivotCsvController extends Controller
{
    public function __invoke(DeliveryImport $deliveryImport, DeliveryReportPivotService $pivotService): StreamedResponse
    {
        Gate::authorize('view', $deliveryImport);

        $pivot = $pivotService->buildMaterialPivot($deliveryImport);
        $filename = 'teslimat-pivot-'.$deliveryImport->id.'.csv';

        return response()->streamDownload(function () use ($pivot): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $materials = $pivot['materials'] ?? [];
            $rows = $pivot['rows'] ?? [];
            $totals = $pivot['totals_row'] ?? [];

            $header = ['TARİH'];
            foreach ($materials as $m) {
                $header[] = $m['label'];
            }
            $header = array_merge($header, [
                'TOPLAM_TON',
                'TOPLAM_ADET',
                'BOS_DOLU_GECERLI',
                'DOLU_DOLU_GECERLI',
                'MALZEME_KISA_METNI',
            ]);
            fputcsv($out, $header, ';');

            foreach ($rows as $r) {
                $line = [$r['tarih'] ?? ''];
                foreach ($materials as $m) {
                    $qty = $r['material_totals'][$m['key']] ?? 0;
                    $cnt = $r['material_counts'][$m['key']] ?? 0;
                    $line[] = number_format((float) $qty, 2, ',', '.').' Ton / '.$cnt.' Adet';
                }
                $line[] = number_format((float) ($r['row_total'] ?? 0), 2, ',', '.');
                $line[] = (string) ($r['row_total_count'] ?? 0);
                $line[] = number_format((float) ($r['boş_dolu'] ?? 0), 2, ',', '.');
                $line[] = number_format((float) ($r['dolu_dolu'] ?? 0), 2, ',', '.');
                $line[] = (string) ($r['malzeme_kisa_metni'] ?? '');
                fputcsv($out, $line, ';');
            }

            if ($rows !== []) {
                $tLine = ['Toplam'];
                foreach ($materials as $m) {
                    $qty = $totals['material_totals'][$m['key']] ?? 0;
                    $cnt = $totals['material_counts'][$m['key']] ?? 0;
                    $tLine[] = number_format((float) $qty, 2, ',', '.').' Ton / '.$cnt.' Adet';
                }
                $tLine[] = number_format((float) ($totals['row_total'] ?? 0), 2, ',', '.');
                $tLine[] = (string) ($totals['row_total_count'] ?? 0);
                $tLine[] = number_format((float) ($totals['boş_dolu'] ?? 0), 2, ',', '.');
                $tLine[] = number_format((float) ($totals['dolu_dolu'] ?? 0), 2, ',', '.');
                $tLine[] = '';
                fputcsv($out, $tLine, ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
