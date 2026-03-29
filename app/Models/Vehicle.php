<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'plate',
    'vin',
    'brand',
    'model',
    'manufacture_year',
    'inspection_valid_until',
    'meta',
])]
class Vehicle extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspection_valid_until' => 'date',
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
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * @return HasMany<FuelIntake, $this>
     */
    public function fuelIntakes(): HasMany
    {
        return $this->hasMany(FuelIntake::class);
    }
}
