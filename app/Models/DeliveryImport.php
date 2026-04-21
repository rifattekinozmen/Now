<?php

namespace App\Models;

use App\Enums\DeliveryImportStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DeliveryImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'reference_no',
    'import_date',
    'source',
    'report_type',
    'file_path',
    'status',
    'row_count',
    'matched_count',
    'unmatched_count',
    'imported_by',
    'notes',
    'last_error',
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

    /**
     * @return HasMany<DeliveryImportRow, $this>
     */
    public function reportRows(): HasMany
    {
        return $this->hasMany(DeliveryImportRow::class);
    }

    protected function petrokokRoutePreference(): Attribute
    {
        return Attribute::get(fn (): string => (string) data_get($this->meta, 'petrokok_route_preference', 'ekinciler'));
    }

    protected function klinkerMatchingOrder(): Attribute
    {
        return Attribute::get(fn (): string => (string) data_get($this->meta, 'klinker_matching_order', 'petrokok_once'));
    }

    protected function klinkerDailyOverrides(): Attribute
    {
        return Attribute::get(function (): array {
            $raw = data_get($this->meta, 'klinker_daily_overrides', []);

            return is_array($raw) ? $raw : [];
        });
    }
}
