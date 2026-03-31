<?php

namespace App\Models;

use App\Enums\AdvanceStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AdvanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'approved_by',
    'voucher_id',
    'amount',
    'currency_code',
    'requested_at',
    'repayment_date',
    'status',
    'reason',
    'rejection_reason',
    'approved_at',
    'meta',
])]
class Advance extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AdvanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status'         => AdvanceStatus::class,
            'amount'         => 'decimal:2',
            'requested_at'   => 'date',
            'repayment_date' => 'date',
            'approved_at'    => 'datetime',
            'meta'           => 'array',
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
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @param Builder<Advance> $query
     * @return Builder<Advance>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AdvanceStatus::Pending->value);
    }

    /**
     * @param Builder<Advance> $query
     * @return Builder<Advance>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', AdvanceStatus::Approved->value);
    }
}
