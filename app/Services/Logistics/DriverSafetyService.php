<?php

namespace App\Services\Logistics;

use App\Models\Employee;
use App\Models\Shipment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Şoför yorgunluk: son penceredeki sevkiyat sürüş süreleri toplamı eşiği aşarsa gönderimi engeller.
 */
final class DriverSafetyService
{
    /**
     * @throws \InvalidArgumentException
     */
    public function assertFatigueAllowsDispatch(Employee $employee): void
    {
        if (! $employee->is_driver) {
            return;
        }

        $maxHours = (float) config('logistics.driver_fatigue.max_driving_hours_in_window', 9.0);
        $lookbackHours = (float) config('logistics.driver_fatigue.lookback_hours', 24.0);

        $hours = $this->dispatchedDrivingHoursInLookback($employee, $lookbackHours);
        if ($hours > $maxHours) {
            throw new \InvalidArgumentException(
                __('Cannot dispatch: driver :name exceeds :hours hours driving in the last :window hours.', [
                    'name' => $employee->fullName(),
                    'hours' => $maxHours,
                    'window' => $lookbackHours,
                ])
            );
        }
    }

    /**
     * Aynı şoför için son lookback saat içindeki sevkiyatlarda (gönderildi → teslim veya şu an) süre toplamı.
     */
    public function dispatchedDrivingHoursInLookback(Employee $employee, float $lookbackHours): float
    {
        $since = now()->subHours((int) ceil($lookbackHours));
        $tenantId = $employee->tenant_id;
        if ($tenantId === null) {
            return 0.0;
        }

        $minutes = Shipment::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '>=', $since)
            ->get()
            ->filter(function (Shipment $shipment) use ($employee): bool {
                $id = data_get($shipment->meta, 'driver_employee_id');

                return (int) $id === (int) $employee->id;
            })
            ->sum(function (Shipment $shipment): float {
                $start = $shipment->dispatched_at;
                if ($start === null) {
                    return 0.0;
                }
                $end = $shipment->delivered_at ?? now();
                $startCarbon = $start instanceof CarbonInterface ? Carbon::instance($start) : Carbon::parse((string) $start);
                $endCarbon = $end instanceof CarbonInterface ? Carbon::instance($end) : Carbon::parse((string) $end);

                return (float) $startCarbon->diffInMinutes($endCarbon);
            });

        return $minutes / 60.0;
    }
}
