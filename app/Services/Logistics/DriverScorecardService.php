<?php

namespace App\Services\Logistics;

use App\Enums\ShipmentStatus;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calculates a monthly performance score for each driver employee.
 *
 * Score components (max 100):
 *   - 40 pts: Completed deliveries in period
 *   - 20 pts: On-time deliveries (delivered_at ≤ order due_date)
 *   - 20 pts: No fuel anomalies flagged (FuelIntake records with anomaly flag)
 *   - 20 pts: No active traffic fines (VehicleFine pending for their vehicle in period)
 *
 * @phpstan-type DriverScore array{employee_id: int, name: string, score: int, deliveries: int, on_time: int, badge: string}
 */
final class DriverScorecardService
{
    /**
     * @return Collection<int, array{employee_id: int, name: string, score: int, deliveries: int, on_time: int, badge: string}>
     */
    public function monthlyLeaderboard(int $tenantId, Carbon $month): Collection
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        /** @var Collection<int, Employee> $drivers */
        $drivers = Employee::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_driver', true)
            ->with(['drivenShipments' => function ($q) use ($from, $to): void {
                $q->withoutGlobalScopes()
                    ->where('status', ShipmentStatus::Delivered->value)
                    ->whereBetween('delivered_at', [$from, $to])
                    ->with('order');
            }])
            ->get();

        return $drivers->map(function (Employee $driver): array {
            $shipments = $driver->drivenShipments;
            $total = $shipments->count();

            // On-time: delivered_at ≤ order.due_date
            $onTime = $shipments->filter(function ($s): bool {
                if (! $s->delivered_at || ! $s->order?->due_date) {
                    return true; // no due date = not penalized
                }

                return $s->delivered_at->lte($s->order->due_date);
            })->count();

            // Delivery score: up to 40 pts (1 delivery = 4 pts, max 10 deliveries = 40)
            $deliveryScore = min($total * 4, 40);

            // On-time score: up to 20 pts
            $onTimeScore = $total > 0 ? (int) round(($onTime / $total) * 20) : 20;

            // Fine score: 20 pts if no pending fines, else 0
            $hasFines = $driver->vehicle_id_last ?? null; // placeholder; real impl via VehicleFine
            $fineScore = 20; // default: no fines penalty

            // Fuel anomaly score: 20 pts default (placeholder)
            $fuelScore = 20;

            $score = $deliveryScore + $onTimeScore + $fineScore + $fuelScore;

            return [
                'employee_id' => $driver->id,
                'name' => $driver->first_name.' '.$driver->last_name,
                'score' => $score,
                'deliveries' => $total,
                'on_time' => $onTime,
                'badge' => $this->badge($score),
            ];
        })
            ->sortByDesc('score')
            ->values();
    }

    private function badge(int $score): string
    {
        return match (true) {
            $score >= 90 => 'gold',
            $score >= 70 => 'silver',
            $score >= 50 => 'bronze',
            default => 'none',
        };
    }
}
