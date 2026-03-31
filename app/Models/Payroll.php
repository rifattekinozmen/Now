<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PayrollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'approved_by',
    'period_start',
    'period_end',
    'gross_salary',
    'deductions',
    'net_salary',
    'currency_code',
    'status',
    'pdf_path',
    'approved_at',
    'paid_at',
    'meta',
])]
class Payroll extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayrollFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status'       => PayrollStatus::class,
            'gross_salary' => 'decimal:2',
            'net_salary'   => 'decimal:2',
            'deductions'   => 'array',
            'period_start' => 'date',
            'period_end'   => 'date',
            'approved_at'  => 'datetime',
            'paid_at'      => 'datetime',
            'meta'         => 'array',
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
     * Gross - net arasındaki farkı dinamik hesaplar.
     */
    public function totalDeductions(): float
    {
        return (float) $this->gross_salary - (float) $this->net_salary;
    }

    /**
     * @param Builder<Payroll> $query
     * @return Builder<Payroll>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PayrollStatus::Draft->value);
    }

    /**
     * @param Builder<Payroll> $query
     * @return Builder<Payroll>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', PayrollStatus::Paid->value);
    }
}
