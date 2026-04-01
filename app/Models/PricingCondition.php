<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'customer_id',
    'name',
    'contract_no',
    'material_code',
    'route_from',
    'route_to',
    'distance_km',
    'base_price',
    'currency_code',
    'price_per_ton',
    'min_tonnage',
    'valid_from',
    'valid_until',
    'is_active',
    'notes',
    'meta',
])]
class PricingCondition extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:1',
            'base_price' => 'decimal:2',
            'price_per_ton' => 'decimal:4',
            'min_tonnage' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('valid_until')
            ->where('valid_until', '<=', now()->addDays($days)->toDateString())
            ->where('valid_until', '>=', now()->toDateString());
    }
}
