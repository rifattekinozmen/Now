<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'delivery_import_id',
    'row_index',
    'row_data',
])]
class DeliveryImportRow extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DeliveryImport, $this>
     */
    public function deliveryImport(): BelongsTo
    {
        return $this->belongsTo(DeliveryImport::class);
    }
}
