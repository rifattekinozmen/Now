<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'key', 'value', 'is_secret'])]
class TenantSetting extends Model
{
    /**
     * Get or return null for the given tenant + key.
     */
    public static function get(int $tenantId, string $key): ?string
    {
        $setting = static::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first();

        if ($setting === null) {
            return null;
        }

        $raw = $setting->value;

        if ($setting->is_secret && $raw !== null) {
            try {
                return decrypt($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return $raw;
    }

    /**
     * Upsert a setting value for the given tenant.
     */
    public static function set(int $tenantId, string $key, ?string $value, bool $isSecret = false): void
    {
        $stored = ($isSecret && $value !== null) ? encrypt($value) : $value;

        static::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $stored, 'is_secret' => $isSecret],
        );
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
