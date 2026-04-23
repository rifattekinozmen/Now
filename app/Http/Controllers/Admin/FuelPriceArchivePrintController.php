<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FuelPrice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class FuelPriceArchivePrintController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('viewAny', FuelPrice::class);

        $countyId = (int) $request->query('county_id', 0);
        $startDate = (string) $request->query('start_date', now()->subDays(30)->toDateString());
        $endDate = (string) $request->query('end_date', now()->toDateString());
        $mode = (string) $request->query('mode', 'print'); // print|pdf

        $rows = [];
        if ($countyId > 0) {
            $base = rtrim((string) config('totalenergies.archive_api_base_url', 'https://apimobile.guzelenerji.com.tr'), '/');

            $response = Http::timeout((int) config('totalenergies.timeout_seconds', 15))
                ->retry(2, 100)
                ->asJson()
                ->post($base.'/exapi/fuel_prices_by_date', [
                    'county_id' => $countyId,
                    'start_date' => $startDate.'T00:00:00Z',
                    'end_date' => $endDate.'T23:59:59Z',
                ]);

            $payload = $response->successful() && is_array($response->json()) ? $response->json() : [];
            if (is_array($payload)) {
                $items = array_values(array_filter($payload, 'is_array'));
                usort($items, function (array $a, array $b): int {
                    return strcmp((string) ($b['pricedate'] ?? ''), (string) ($a['pricedate'] ?? ''));
                });

                $count = count($items);
                for ($i = 0; $i < $count; $i++) {
                    $currentMotorin = isset($items[$i]['motorin']) && is_numeric($items[$i]['motorin']) ? (float) $items[$i]['motorin'] : null;
                    $previousMotorin = ($i > 0 && isset($items[$i - 1]['motorin']) && is_numeric($items[$i - 1]['motorin']))
                        ? (float) $items[$i - 1]['motorin']
                        : null;

                    $delta = null;
                    if ($currentMotorin !== null && $previousMotorin !== null && abs($previousMotorin) > 0.000001) {
                        $delta = (($currentMotorin - $previousMotorin) / $previousMotorin) * 100;
                    }

                    $rows[] = [
                        'pricedate' => isset($items[$i]['pricedate']) ? Carbon::parse((string) $items[$i]['pricedate'])->format('d.m.Y') : '-',
                        'kursunsuz_95_excellium_95' => $items[$i]['kursunsuz_95_excellium_95'] ?? null,
                        'motorin' => $items[$i]['motorin'] ?? null,
                        'motorin_change_prev_pct' => $delta,
                        'motorin_excellium' => $items[$i]['motorin_excellium'] ?? null,
                        'kalorifer_yakiti' => $items[$i]['kalorifer_yakiti'] ?? null,
                        'fuel_oil' => $items[$i]['fuel_oil'] ?? null,
                        'yuksek_kukurtlu_fuel_oil' => $items[$i]['yuksek_kukurtlu_fuel_oil'] ?? null,
                        'otogaz' => $items[$i]['otogaz'] ?? null,
                        'gazyagi' => $items[$i]['gazyagi'] ?? null,
                    ];
                }
            }
        }

        return view('admin.fuel-prices.archive-print', [
            'rows' => $rows,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'mode' => $mode,
        ]);
    }
}
