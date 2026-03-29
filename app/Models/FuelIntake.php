<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FuelIntakeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'vehicle_id',
    'liters',
    'odometer_km',
    'recorded_at',
    'meta',
])]
class FuelIntake extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<FuelIntakeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (FuelIntake $row): void {
            if ($row->getAttribute('tenant_id') !== null) {
                return;
            }
            $vehicleId = $row->getAttribute('vehicle_id');
            if ($vehicleId === null) {
                return;
            }
            $tenantId = Vehicle::query()->withoutGlobalScopes()
                ->whereKey($vehicleId)
                ->value('tenant_id');
            if ($tenantId !== null) {
                $row->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'liters' => 'decimal:3',
            'odometer_km' => 'decimal:2',
            'recorded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
