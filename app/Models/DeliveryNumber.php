<?php

namespace App\Models;

use App\Enums\DeliveryNumberStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DeliveryNumberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'pin_code',
    'sas_no',
    'status',
    'order_id',
    'shipment_id',
    'assigned_at',
    'used_at',
    'meta',
])]
class DeliveryNumber extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DeliveryNumberFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryNumberStatus::class,
            'assigned_at' => 'datetime',
            'used_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
