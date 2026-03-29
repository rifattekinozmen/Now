<?php

namespace App\Livewire\Concerns;

use App\Authorization\LogisticsPermission;

trait RequiresLogisticsAdmin
{
    protected function ensureLogisticsAdmin(): void
    {
        $user = request()->user();
        abort_unless($user !== null && $user->can(LogisticsPermission::ADMIN), 403);
    }

    /**
     * Modül yazma: tam `logistics.admin` veya ilgili `logistics.*.write` izni.
     */
    protected function ensureLogisticsWrite(string $writePermission): void
    {
        $user = request()->user();
        abort_unless(
            $user !== null && LogisticsPermission::canWrite($user, $writePermission),
            403
        );
    }
}
