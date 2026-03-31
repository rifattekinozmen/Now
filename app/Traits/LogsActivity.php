<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Attach to Eloquent models to auto-record created/updated/deleted events.
 * Also exposes activityLogs() relationship for display in detail pages.
 *
 * @mixin Model
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model): void {
            ActivityLog::log($model, 'created');
        });

        static::updated(function (Model $model): void {
            $changed = array_keys($model->getDirty());
            ActivityLog::log($model, 'updated', null, ['changed' => $changed]);
        });

        static::deleted(function (Model $model): void {
            ActivityLog::log($model, 'deleted');
        });
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'subject_id')
            ->where('subject_type', static::class)
            ->orderByDesc('created_at');
    }
}
