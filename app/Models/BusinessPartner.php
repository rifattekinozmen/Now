<?php

namespace App\Models;

use App\Enums\BusinessPartnerType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BusinessPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'name',
    'type',
    'tax_no',
    'contact_person',
    'phone',
    'email',
    'address',
    'city',
    'country',
    'iban',
    'payment_terms_days',
    'is_active',
    'notes',
    'meta',
])]
class BusinessPartner extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BusinessPartnerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BusinessPartnerType::class,
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
     * @param  Builder<BusinessPartner>  $query
     * @return Builder<BusinessPartner>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
