<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\LogsActivity;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id',
    'order_id',
    'vehicle_id',
    'driver_employee_id',
    'status',
    'dispatched_at',
    'delivered_at',
    'meta',
    'public_reference_token',
    'pod_payload',
])]
class Shipment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    use LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment): void {
            if ($shipment->getAttribute('tenant_id') === null) {
                $orderId = $shipment->getAttribute('order_id');
                if ($orderId !== null) {
                    $tenantId = Order::query()->withoutGlobalScopes()
                        ->whereKey($orderId)
                        ->value('tenant_id');

                    if ($tenantId !== null) {
                        $shipment->setAttribute('tenant_id', $tenantId);
                    }
                }
            }

            if (blank($shipment->getAttribute('public_reference_token'))) {
                $shipment->setAttribute('public_reference_token', Str::random(48));
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
            'pod_payload' => 'array',
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
     * @return BelongsTo<Employee, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_employee_id');
    }

    /**
     * @return HasMany<DeliveryNumber, $this>
     */
    public function deliveryNumbers(): HasMany
    {
        return $this->hasMany(DeliveryNumber::class);
    }
}
