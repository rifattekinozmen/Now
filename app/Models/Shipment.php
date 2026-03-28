<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'order_id',
    'vehicle_id',
    'status',
    'dispatched_at',
    'delivered_at',
    'meta',
])]
class Shipment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment): void {
            if ($shipment->getAttribute('tenant_id') !== null) {
                return;
            }

            $orderId = $shipment->getAttribute('order_id');
            if ($orderId === null) {
                return;
            }

            $tenantId = Order::query()->withoutGlobalScopes()
                ->whereKey($orderId)
                ->value('tenant_id');

            if ($tenantId !== null) {
                $shipment->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<DeliveryNumber, $this>
     */
    public function deliveryNumbers(): HasMany
    {
        return $this->hasMany(DeliveryNumber::class);
    }
}
