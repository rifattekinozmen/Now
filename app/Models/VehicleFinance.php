<?php

namespace App\Models;

use App\Enums\VehicleFinanceType;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'vehicle_id',
    'finance_type',
    'amount',
    'currency_code',
    'transaction_date',
    'due_date',
    'paid_at',
    'reference_no',
    'description',
    'meta',
])]
class VehicleFinance extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finance_type' => VehicleFinanceType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
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
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
