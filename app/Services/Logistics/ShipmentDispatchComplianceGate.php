<?php

namespace App\Services\Logistics;

use App\Models\Employee;
use App\Models\Shipment;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Sevkiyat gönderiminden önce muayene ve (atanmışsa) şoför belgeleri için hard-stop.
 */
final class ShipmentDispatchComplianceGate
{
    public function __construct(
        private DriverSafetyService $driverSafety,
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public function assertDispatchAllowed(Shipment $shipment): void
    {
        $shipment->loadMissing('vehicle');

        if ($shipment->vehicle_id !== null) {
            $vehicle = $shipment->vehicle;
            if ($vehicle instanceof Vehicle) {
                $this->assertVehicleInspectionAllowsDispatch($vehicle);
            }
        }

        $driverId = data_get($shipment->meta, 'driver_employee_id');
        if (is_numeric($driverId) && $shipment->tenant_id !== null) {
            $employee = Employee::query()
                ->where('tenant_id', $shipment->tenant_id)
                ->whereKey((int) $driverId)
                ->first();
            if ($employee instanceof Employee) {
                $this->assertDriverDocumentsAllowDispatch($employee);
                $this->driverSafety->assertFatigueAllowsDispatch($employee);
            }
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertVehicleInspectionAllowsDispatch(Vehicle $vehicle): void
    {
        $until = $vehicle->inspection_valid_until;
        if ($until === null) {
            throw new \InvalidArgumentException(
                __('Cannot dispatch: vehicle :plate has no inspection valid-until date.', ['plate' => $vehicle->plate])
            );
        }

        $today = now()->startOfDay();
        $expiry = $until instanceof CarbonInterface
            ? $until->copy()->startOfDay()
            : Carbon::parse((string) $until)->startOfDay();

        if ($expiry->lt($today)) {
            throw new \InvalidArgumentException(
                __('Cannot dispatch: vehicle :plate inspection expired on :date.', [
                    'plate' => $vehicle->plate,
                    'date' => $until instanceof CarbonInterface
                        ? $until->format('Y-m-d')
                        : (string) $until,
                ])
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertDriverDocumentsAllowDispatch(Employee $employee): void
    {
        if (! $employee->is_driver) {
            return;
        }

        $today = now()->startOfDay();

        $license = $employee->license_valid_until;
        if ($license !== null) {
            $d = $license instanceof CarbonInterface
                ? $license->copy()->startOfDay()
                : Carbon::parse((string) $license)->startOfDay();
            if ($d->lt($today)) {
                throw new \InvalidArgumentException(
                    __('Cannot dispatch: driver :name has an expired driving license.', ['name' => $employee->fullName()])
                );
            }
        }

        $src = $employee->src_valid_until;
        if ($src !== null) {
            $d = $src instanceof CarbonInterface
                ? $src->copy()->startOfDay()
                : Carbon::parse((string) $src)->startOfDay();
            if ($d->lt($today)) {
                throw new \InvalidArgumentException(
                    __('Cannot dispatch: driver :name has an expired SRC certificate.', ['name' => $employee->fullName()])
                );
            }
        }

        $psy = $employee->psychotechnical_valid_until;
        if ($psy !== null) {
            $d = $psy instanceof CarbonInterface
                ? $psy->copy()->startOfDay()
                : Carbon::parse((string) $psy)->startOfDay();
            if ($d->lt($today)) {
                throw new \InvalidArgumentException(
                    __('Cannot dispatch: driver :name has an expired psychotechnical certificate.', ['name' => $employee->fullName()])
                );
            }
        }
    }
}
