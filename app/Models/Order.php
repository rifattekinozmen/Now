<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'customer_id',
    'order_number',
    'sas_no',
    'status',
    'ordered_at',
    'currency_code',
    'freight_amount',
    'exchange_rate',
    'distance_km',
    'tonnage',
    'gross_weight_kg',
    'tara_weight_kg',
    'net_weight_kg',
    'moisture_percent',
    'incoterms',
    'loading_site',
    'unloading_site',
    'meta',
])]
class Order extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if ($order->getAttribute('tenant_id') !== null) {
                return;
            }

            $customerId = $order->getAttribute('customer_id');
            if ($customerId === null) {
                return;
            }

            $tenantId = Customer::query()->withoutGlobalScopes()
                ->whereKey($customerId)
                ->value('tenant_id');

            if ($tenantId !== null) {
                $order->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'ordered_at' => 'datetime',
            'freight_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'distance_km' => 'decimal:2',
            'tonnage' => 'decimal:3',
            'gross_weight_kg' => 'decimal:3',
            'tara_weight_kg' => 'decimal:3',
            'net_weight_kg' => 'decimal:3',
            'moisture_percent' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * @return HasMany<DeliveryNumber, $this>
     */
    public function deliveryNumbers(): HasMany
    {
        return $this->hasMany(DeliveryNumber::class);
    }
}
