<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\Advance;
use App\Models\User;

class AdvancePolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, Advance $advance): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $advance->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::ADVANCES_WRITE);
    }

    public function update(User $user, Advance $advance): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::ADVANCES_WRITE)
            && (int) $user->tenant_id === (int) $advance->tenant_id;
    }

    /** Maker-Checker: only admin can approve */
    public function approve(User $user, Advance $advance): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $advance->tenant_id
            && $advance->status->isPending();
    }

    public function delete(User $user, Advance $advance): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $advance->tenant_id;
    }
}
