<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FuelPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'fuel_type',
    'price',
    'currency',
    'recorded_at',
    'source',
    'region',
])]
class FuelPrice extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<FuelPriceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'recorded_at' => 'date',
        ];
    }
}
