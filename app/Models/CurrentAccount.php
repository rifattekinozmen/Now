<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'customer_id',
    'employee_id',
    'vehicle_id',
    'account_type',
    'code',
    'name',
    'balance',
    'currency_code',
    'credit_limit',
    'payment_term_days',
    'is_active',
    'notes',
    'meta',
])]
class CurrentAccount extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'payment_term_days' => 'integer',
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

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<AccountTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class)->orderByDesc('transaction_date');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('account_type', $type->value);
    }
}
