<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MaintenanceScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'vehicle_id',
    'assigned_to',
    'title',
    'type',
    'status',
    'scheduled_date',
    'completed_date',
    'km_at_service',
    'next_km',
    'cost',
    'service_provider',
    'notes',
    'meta',
])]
class MaintenanceSchedule extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MaintenanceScheduleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type'           => MaintenanceType::class,
            'status'         => MaintenanceStatus::class,
            'scheduled_date' => 'date',
            'completed_date' => 'date',
            'cost'           => 'decimal:2',
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
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    /**
     * @param Builder<MaintenanceSchedule> $query
     * @return Builder<MaintenanceSchedule>
     */
    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->where('status', MaintenanceStatus::Scheduled->value)
            ->whereBetween('scheduled_date', [now(), now()->addDays($days)]);
    }

    /**
     * @param Builder<MaintenanceSchedule> $query
     * @return Builder<MaintenanceSchedule>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', MaintenanceStatus::Scheduled->value)
            ->where('scheduled_date', '<', now());
    }
}
