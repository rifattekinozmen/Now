<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LeaveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'approved_by',
    'type',
    'status',
    'start_date',
    'end_date',
    'days_count',
    'reason',
    'rejection_reason',
    'approved_at',
    'meta',
])]
class Leave extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LeaveFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type'        => LeaveType::class,
            'status'      => LeaveStatus::class,
            'start_date'  => 'date',
            'end_date'    => 'date',
            'approved_at' => 'datetime',
            'meta'        => 'array',
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
     * @param Builder<Leave> $query
     * @return Builder<Leave>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Pending->value);
    }

    /**
     * @param Builder<Leave> $query
     * @return Builder<Leave>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Approved->value);
    }
}
