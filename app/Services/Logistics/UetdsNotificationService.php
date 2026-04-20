<?php

namespace App\Services\Logistics;

use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * U-ETDS (Ulusal Elektronik Tebligat Dağıtım Sistemi) sefer bildirimi.
 *
 * Türkiye'de karayolu taşıma mevzuatı gereği her sefer bildirilmelidir.
 *
 * Config (config/logistics.php):
 *   uetds.enabled   — bool  (default false)
 *   uetds.api_url   — string
 *   uetds.api_key   — string
 *
 * @phpstan-type UetdsResult array{success: bool, reference_no: string|null, message: string}
 */
final class UetdsNotificationService
{
    public function isEnabled(): bool
    {
        return (bool) config('logistics.uetds.enabled', false);
    }

    /**
     * Submit a shipment sefer (journey) notification to U-ETDS.
     *
     * @return UetdsResult
     */
    public function notify(Shipment $shipment): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'reference_no' => null, 'message' => 'U-ETDS disabled'];
        }

        $apiUrl = config('logistics.uetds.api_url');
        $apiKey = config('logistics.uetds.api_key');

        if (! filled($apiUrl) || ! filled($apiKey)) {
            Log::warning('UetdsNotificationService: API URL or key not configured');

            return ['success' => false, 'reference_no' => null, 'message' => 'API credentials missing'];
        }

        try {
            $payload = $this->buildPayload($shipment);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl.'/seferler', $payload);

            if ($response->successful()) {
                $refNo = $response->json('referans_no') ?? $response->json('reference_no');

                // Store the U-ETDS reference in shipment meta
                $meta = $shipment->meta ?? [];
                $meta['uetds'] = [
                    'reference_no' => $refNo,
                    'submitted_at' => now()->toIso8601String(),
                    'status' => 'submitted',
                ];
                $shipment->update(['meta' => $meta]);

                Log::info('UetdsNotificationService: submitted', ['shipment_id' => $shipment->id, 'ref' => $refNo]);

                return ['success' => true, 'reference_no' => $refNo, 'message' => 'Submitted'];
            }

            Log::warning('UetdsNotificationService: API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'reference_no' => null, 'message' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('UetdsNotificationService: Exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'reference_no' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Shipment $shipment): array
    {
        $order = $shipment->order;
        $vehicle = $shipment->vehicle;
        $driver = $shipment->driver;

        return [
            'plaka' => $vehicle?->plate,
            'surucu_ad_soyad' => $driver ? $driver->first_name.' '.$driver->last_name : null,
            'yukleme_adresi' => $order?->loading_site,
            'teslimat_adresi' => $order?->unloading_site,
            'sefer_tarihi' => $shipment->dispatched_at?->toDateString(),
            'referans_no' => $shipment->public_reference_token,
            'yuk_cinsi' => $order?->cargo_type,
            'tonaj' => $order?->tonnage,
        ];
    }
}
