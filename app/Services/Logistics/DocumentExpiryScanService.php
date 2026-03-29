<?php

namespace App\Services\Logistics;

use App\Contracts\Operations\OperationalNotifier;
use App\Models\Employee;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * Araç muayenesi ve şoför belgeleri için 30/15/7/1 gün kala operasyonel uyarı (prototip).
 */
final class DocumentExpiryScanService
{
    /** @var list<int> */
    public const THRESHOLD_DAYS = [1, 7, 15, 30];

    public function __construct(
        private OperationalNotifier $notifier,
    ) {}

    /**
     * Bugünden itibaren eşik günlerde sona erecek kayıtlar için bildirim üretir.
     *
     * @return int Gönderilen bildirim sayısı
     */
    public function scan(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now()->startOfDay();
        $sent = 0;

        foreach (self::THRESHOLD_DAYS as $daysRemaining) {
            $expiryOn = $today->addDays($daysRemaining);

            $sent += $this->notifyVehicles($expiryOn, $daysRemaining);
            $sent += $this->notifyEmployeeDates($expiryOn, $daysRemaining);
        }

        return $sent;
    }

    private function notifyVehicles(CarbonImmutable $expiryOn, int $daysRemaining): int
    {
        $count = 0;
        Vehicle::query()
            ->whereDate('inspection_valid_until', $expiryOn->toDateString())
            ->orderBy('id')
            ->each(function (Vehicle $vehicle) use ($daysRemaining, &$count): void {
                $this->notifier->notify('logistics.document_expiry_due', [
                    'entity' => 'vehicle_inspection',
                    'tenant_id' => $vehicle->tenant_id,
                    'vehicle_id' => $vehicle->id,
                    'plate' => $vehicle->plate,
                    'expires_on' => $vehicle->inspection_valid_until?->toDateString(),
                    'days_remaining' => $daysRemaining,
                ]);
                $count++;
            });

        return $count;
    }

    private function notifyEmployeeDates(CarbonImmutable $expiryOn, int $daysRemaining): int
    {
        $count = 0;
        $date = $expiryOn->toDateString();

        $fields = [
            'license_valid_until' => 'employee_license',
            'src_valid_until' => 'employee_src',
            'psychotechnical_valid_until' => 'employee_psychotechnical',
        ];

        foreach ($fields as $column => $entity) {
            Employee::query()
                ->whereDate($column, $date)
                ->orderBy('id')
                ->each(function (Employee $employee) use ($column, $entity, $daysRemaining, &$count): void {
                    $expires = $employee->getAttribute($column);
                    $this->notifier->notify('logistics.document_expiry_due', [
                        'entity' => $entity,
                        'tenant_id' => $employee->tenant_id,
                        'employee_id' => $employee->id,
                        'name' => $employee->fullName(),
                        'expires_on' => $expires instanceof \DateTimeInterface ? $expires->format('Y-m-d') : null,
                        'days_remaining' => $daysRemaining,
                    ]);
                    $count++;
                });
        }

        return $count;
    }
}
