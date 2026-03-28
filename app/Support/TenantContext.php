<?php

namespace App\Support;

final class TenantContext
{
    public static function id(): ?int
    {
        return auth()->user()?->tenant_id;
    }
}
