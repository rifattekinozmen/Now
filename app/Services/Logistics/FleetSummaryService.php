<?php

namespace App\Services\Logistics;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

/**
 * Fleet/araç KPI özeti — dashboard widget için kiracı kapsamlı sorgular.
 */
class FleetSummaryService
{
    /**
     * @return array{total_vehicles: int, inspection_due_30d: int, active_shipments: int}
     */
    public function getFleetKpi(int $tenantId): array
    {
        $now = now()->toDateString();
        $in30 = now()->addDays(30)->toDateString();

        $row = DB::selectOne(
            'SELECT
                (SELECT COUNT(*) FROM vehicles WHERE tenant_id = ?) AS total_vehicles,
                (SELECT COUNT(*) FROM vehicles WHERE tenant_id = ? AND inspection_valid_until IS NOT NULL AND inspection_valid_until BETWEEN ? AND ?) AS inspection_due_30d',
            [$tenantId, $tenantId, $now, $in30]
        );

        $activeShipments = Shipment::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value])
            ->count();

        return [
            'total_vehicles' => (int) ($row->total_vehicles ?? 0),
            'inspection_due_30d' => (int) ($row->inspection_due_30d ?? 0),
            'active_shipments' => $activeShipments,
        ];
    }
}
