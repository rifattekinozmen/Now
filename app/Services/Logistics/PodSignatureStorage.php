<?php

namespace App\Services\Logistics;

use App\Models\Shipment;
use Illuminate\Support\Facades\Storage;

/**
 * Stores POD canvas signatures as PNG on the local disk (no extra Composer deps).
 */
final class PodSignatureStorage
{
    private const int MAX_BYTES = 524_288;

    private const string PNG_MAGIC = "\x89PNG\r\n\x1a\n";

    /**
     * Decode a data URL, validate PNG payload, write to storage, return path relative to the local disk.
     *
     * @throws \InvalidArgumentException
     */
    public function storePngFromDataUrl(Shipment $shipment, string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new \InvalidArgumentException(__('Invalid signature image format.'));
        }

        $encoded = substr($dataUrl, strlen('data:image/png;base64,'));
        $encoded = preg_replace('/\s+/', '', $encoded) ?? '';

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            throw new \InvalidArgumentException(__('Invalid signature encoding.'));
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw new \InvalidArgumentException(__('Signature image is too large.'));
        }

        if (! str_starts_with($binary, self::PNG_MAGIC)) {
            throw new \InvalidArgumentException(__('Signature must be a PNG image.'));
        }

        $tenantId = (int) $shipment->tenant_id;
        $shipmentId = (int) $shipment->id;
        $relativePath = "pod-signatures/{$tenantId}/{$shipmentId}.png";

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

    /**
     * True when path matches the expected tenant/shipment layout (mitigate path traversal).
     */
    public function pathBelongsToShipment(Shipment $shipment, string $relativePath): bool
    {
        $expected = 'pod-signatures/'.(int) $shipment->tenant_id.'/'.(int) $shipment->id.'.png';

        return $relativePath === $expected;
    }
}
