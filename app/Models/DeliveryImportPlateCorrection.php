<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'delivery_import_id',
    'delivery_import_row_id',
    'row_index',
    'old_plate',
    'new_plate',
    'status',
    'request_reason',
    'requested_by',
    'reviewed_by',
    'reviewed_at',
    'applied_at',
    'review_note',
])]
class DeliveryImportPlateCorrection extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DeliveryImport, $this>
     */
    public function deliveryImport(): BelongsTo
    {
        return $this->belongsTo(DeliveryImport::class);
    }

    /**
     * @return BelongsTo<DeliveryImportRow, $this>
     */
    public function deliveryImportRow(): BelongsTo
    {
        return $this->belongsTo(DeliveryImportRow::class, 'delivery_import_row_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
