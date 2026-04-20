<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'partner_number',
    'tax_id',
    'tax_office_id',
    'legal_name',
    'trade_name',
    'payment_term_days',
    'mersis_no',
    'kep_address',
    'credit_limit',
    'credit_currency_code',
    'is_blacklisted',
    'meta',
])]
class Customer extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'is_blacklisted' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * Virtual "name" attribute — returns legal_name for display convenience.
     * Avoids raw SQL errors when code references $customer->name.
     */
    public function getNameAttribute(): string
    {
        return $this->legal_name ?? $this->trade_name ?? '';
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return BelongsTo<TaxOffice, $this>
     */
    public function taxOffice(): BelongsTo
    {
        return $this->belongsTo(TaxOffice::class);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasMany<CustomerContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }
}
