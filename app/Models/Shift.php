<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\LogsActivity;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'shift_date',
    'start_time',
    'end_time',
    'shift_type',
    'status',
    'notes',
    'meta',
])]
class Shift extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'shift_type' => ShiftType::class,
            'status' => ShiftStatus::class,
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
