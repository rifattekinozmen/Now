<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Enums\DocumentFileType;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\LogsActivity;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'tenant_id',
    'documentable_type',
    'documentable_id',
    'title',
    'file_path',
    'file_type',
    'file_size',
    'category',
    'expires_at',
    'uploaded_by',
    'notes',
    'meta',
])]
class Document extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_type' => DocumentFileType::class,
            'category' => DocumentCategory::class,
            'expires_at' => 'date',
            'file_size' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return ! $this->isExpired() && $this->expires_at->lte(Carbon::now()->addDays($days));
    }
}
