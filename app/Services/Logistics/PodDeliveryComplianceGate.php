<?php

namespace App\Services\Logistics;

/**
 * iPOD sıkı modu: imza + GPS + fotoğraf yolu zorunluluğu (config: logistics.ipod.strict).
 */
final class PodDeliveryComplianceGate
{
    /**
     * @param  array{note?: string, received_by?: string, signature_data_url?: string, latitude?: float|string, longitude?: float|string, photo_storage_path?: string}|null  $pod
     *
     * @throws \InvalidArgumentException
     */
    public function assertDeliveredProofAllowed(?array $pod): void
    {
        if (! (bool) config('logistics.ipod.strict', false)) {
            return;
        }

        $pod = $pod ?? [];

        $sig = isset($pod['signature_data_url']) ? trim((string) $pod['signature_data_url']) : '';
        if ($sig === '') {
            throw new \InvalidArgumentException(
                __('Strict POD mode requires a delivery signature.')
            );
        }

        $lat = $pod['latitude'] ?? null;
        $lng = $pod['longitude'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            throw new \InvalidArgumentException(
                __('Strict POD mode requires delivery latitude and longitude.')
            );
        }

        $photo = isset($pod['photo_storage_path']) ? trim((string) $pod['photo_storage_path']) : '';
        if ($photo === '') {
            throw new \InvalidArgumentException(
                __('Strict POD mode requires a delivery photo storage path.')
            );
        }
    }
}
