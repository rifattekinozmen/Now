<?php

namespace App\Support;

final class TenantContext
{
    public static function id(): ?int
    {
        $user = auth()->user();

        return $user?->active_tenant_id ?? $user?->tenant_id;
    }
}
