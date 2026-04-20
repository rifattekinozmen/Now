<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VehicleGpsPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'vehicle_id',
    'lat',
    'lng',
    'speed',
    'heading',
    'recorded_at',
])]
class VehicleGpsPosition extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VehicleGpsPositionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'speed' => 'float',
            'heading' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Latest GPS position for a given vehicle.
     */
    public static function latestForVehicle(int $vehicleId): ?self
    {
        return static::query()
            ->where('vehicle_id', $vehicleId)
            ->latest('recorded_at')
            ->first();
    }

    /**
     * Scope: positions older than N days (for scheduled cleanup).
     *
     * @param  Builder<VehicleGpsPosition>  $query
     * @return Builder<VehicleGpsPosition>
     */
    public function scopeOlderThan(Builder $query, int $days): Builder
    {
        return $query->where('recorded_at', '<', now()->subDays($days));
    }
}
