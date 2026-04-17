<?php

namespace App\Models;

use App\Enums\DeliveryImportStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DeliveryImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id',
    'reference_no',
    'import_date',
    'source',
    'file_path',
    'status',
    'row_count',
    'matched_count',
    'unmatched_count',
    'imported_by',
    'notes',
    'meta',
])]
class DeliveryImport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DeliveryImportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryImportStatus::class,
            'import_date' => 'date',
            'meta' => 'array',
        ];
    }
}
