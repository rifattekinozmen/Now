<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CbamReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'shipment_id',
    'co2_kg',
    'distance_km',
    'fuel_consumption_l',
    'tonnage',
    'vehicle_type',
    'report_date',
    'status',
    'meta',
])]
class CbamReport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CbamReportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'co2_kg' => 'float',
            'distance_km' => 'float',
            'fuel_consumption_l' => 'float',
            'tonnage' => 'float',
            'report_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
