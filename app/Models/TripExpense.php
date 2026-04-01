<?php

namespace App\Models;

use App\Enums\ExpenseType;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'vehicle_id',
    'employee_id',
    'shipment_id',
    'expense_type',
    'amount',
    'currency_code',
    'expense_date',
    'odometer_km',
    'receipt_path',
    'description',
    'meta',
])]
class TripExpense extends Model
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
            'expense_type' => ExpenseType::class,
            'amount' => 'decimal:2',
            'odometer_km' => 'decimal:1',
            'expense_date' => 'date',
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

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
