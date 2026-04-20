<?php

namespace App\Models;

use App\Enums\VehicleFineStatus;
use App\Enums\VehicleFineType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VehicleFineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'vehicle_id',
    'fine_date',
    'amount',
    'currency_code',
    'fine_type',
    'fine_no',
    'location',
    'status',
    'paid_at',
    'document_path',
    'notes',
    'meta',
])]
class VehicleFine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VehicleFineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fine_date' => 'date',
            'amount' => 'decimal:2',
            'fine_type' => VehicleFineType::class,
            'status' => VehicleFineStatus::class,
            'paid_at' => 'datetime',
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

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
