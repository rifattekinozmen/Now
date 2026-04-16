<?php

namespace App\Services\Logistics;

use App\Models\AppNotification;
use App\Models\FuelIntake;
use App\Models\User;

/**
 * Yakıt anomalisi tespiti: son iki dolum arasındaki km farkı ile litre miktarını karşılaştırarak
 * %15 eşiğini aşan tüketim sapmalarını AppNotification olarak kayıt eder.
 */
final class FuelAnomalyService
{
    /**
     * L/100 km anomali eşiği (varsayılan %15 sapma).
     */
    public const ANOMALY_THRESHOLD_PERCENT = 15.0;

    /**
     * Son dolum ile kıyasla anomali varsa AnomalyResult döner.
     */
    public function analyze(FuelIntake $intake): AnomalyResult
    {
        $vehicleId = $intake->vehicle_id;

        // Referans için bu dolumdan önceki en son iki kayıt (km sırasıyla)
        $previous = FuelIntake::query()
            ->withoutGlobalScopes()
            ->where('vehicle_id', $vehicleId)
            ->where('id', '!=', $intake->id)
            ->whereNotNull('odometer_km')
            ->orderByDesc('odometer_km')
            ->limit(5)
            ->get();

        if ($previous->count() < 2) {
            return new AnomalyResult(isAnomaly: false);
        }

        // En son önceki dolum (odometer_km < mevcut dolumun km'si)
        $currentOdo = (float) $intake->odometer_km;
        $prevIntake = $previous->first(fn (FuelIntake $fi) => (float) $fi->odometer_km < $currentOdo);

        if ($prevIntake === null) {
            return new AnomalyResult(isAnomaly: false);
        }

        $prevPrevIntake = $previous->first(
            fn (FuelIntake $fi) => (float) $fi->odometer_km < (float) $prevIntake->odometer_km
        );

        if ($prevPrevIntake === null) {
            return new AnomalyResult(isAnomaly: false);
        }

        // Önceki aralıktaki tüketim (L/100km)
        $refDistance = (float) $prevIntake->odometer_km - (float) $prevPrevIntake->odometer_km;
        if ($refDistance <= 0) {
            return new AnomalyResult(isAnomaly: false);
        }

        $refConsumption = ((float) $prevIntake->liters / $refDistance) * 100.0;

        // Mevcut aralık tüketimi
        $curDistance = $currentOdo - (float) $prevIntake->odometer_km;
        if ($curDistance <= 0) {
            return new AnomalyResult(isAnomaly: false);
        }

        $curConsumption = ((float) $intake->liters / $curDistance) * 100.0;

        if ($refConsumption <= 0) {
            return new AnomalyResult(isAnomaly: false);
        }

        $deviationPercent = abs($curConsumption - $refConsumption) / $refConsumption * 100.0;

        if ($deviationPercent < self::ANOMALY_THRESHOLD_PERCENT) {
            return new AnomalyResult(isAnomaly: false);
        }

        return new AnomalyResult(
            isAnomaly: true,
            currentConsumption: $curConsumption,
            referenceConsumption: $refConsumption,
            deviationPercent: $deviationPercent,
        );
    }

    /**
     * Anomali varsa tenant admin kullanıcılarına AppNotification oluşturur.
     */
    public function notifyIfAnomaly(FuelIntake $intake): AnomalyResult
    {
        $result = $this->analyze($intake);

        if (! $result->isAnomaly) {
            return $result;
        }

        $tenantId = $intake->tenant_id;
        $vehiclePlate = $intake->vehicle?->plate_number ?? "#{$intake->vehicle_id}";

        // Tenant'ın admin kullanıcılarına bildirim gönder
        User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'tenant-user'))
            ->get()
            ->each(function (User $user) use ($intake, $result, $vehiclePlate, $tenantId): void {
                AppNotification::query()->create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'type' => 'fuel_anomaly',
                    'title' => __('Fuel Anomaly Detected'),
                    'body' => __('Vehicle :plate consumption deviated :pct% from reference (:ref L/100km → :cur L/100km).', [
                        'plate' => $vehiclePlate,
                        'pct' => round($result->deviationPercent, 1),
                        'ref' => round($result->referenceConsumption, 2),
                        'cur' => round($result->currentConsumption, 2),
                    ]),
                    'is_read' => false,
                    'data' => [
                        'fuel_intake_id' => $intake->id,
                        'vehicle_id' => $intake->vehicle_id,
                        'deviation_percent' => $result->deviationPercent,
                        'current_consumption' => $result->currentConsumption,
                        'reference_consumption' => $result->referenceConsumption,
                    ],
                    'url' => null,
                ]);
            });

        return $result;
    }
}
