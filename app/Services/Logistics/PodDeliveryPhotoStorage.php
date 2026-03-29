<?php

namespace App\Services\Logistics;

use App\Models\Shipment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Teslimat kanıtı fotoğrafı (sıkı iPOD modu) — yerel disk.
 */
final class PodDeliveryPhotoStorage
{
    private const int MAX_BYTES = 2_097_152;

    /** @var list<string> */
    private const array ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * @throws \InvalidArgumentException
     */
    public function storeFromUpload(Shipment $shipment, UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException(__('Delivery photo must be JPEG, PNG, or WebP.'));
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException(__('Delivery photo is too large.'));
        }

        $binary = $file->getContent();
        if ($binary === '') {
            throw new \InvalidArgumentException(__('Could not read delivery photo.'));
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $tenantId = (int) $shipment->tenant_id;
        $shipmentId = (int) $shipment->id;
        $relativePath = "pod-delivery-photos/{$tenantId}/{$shipmentId}.".Str::lower($ext);

        Storage::disk('local')->put($relativePath, $binary);

        return $relativePath;
    }

    /**
     * @param  non-empty-string  $relativePath
     */
    public function exists(string $relativePath): bool
    {
        return Storage::disk('local')->exists($relativePath);
    }

    /**
     * @param  non-empty-string  $relativePath
     */
    public function get(string $relativePath): string
    {
        return Storage::disk('local')->get($relativePath);
    }

    public function mimeForPath(string $relativePath): string
    {
        return match (Str::lower((string) pathinfo($relativePath, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    public function pathBelongsToShipment(Shipment $shipment, string $relativePath): bool
    {
        $prefix = 'pod-delivery-photos/'.(int) $shipment->tenant_id.'/'.(int) $shipment->id.'.';

        return str_starts_with($relativePath, $prefix);
    }
}
