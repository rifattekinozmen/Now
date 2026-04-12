<?php

namespace App\Models;

use App\Enums\TyrePosition;
use App\Enums\TyreStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VehicleTyreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'vehicle_id',
    'brand',
    'size',
    'position',
    'installed_at',
    'km_installed',
    'removed_at',
    'km_removed',
    'status',
    'tread_depth_mm',
    'supplier',
    'notes',
    'meta',
])]
class VehicleTyre extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VehicleTyreFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => TyrePosition::class,
            'status' => TyreStatus::class,
            'installed_at' => 'date',
            'removed_at' => 'date',
            'tread_depth_mm' => 'decimal:1',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
