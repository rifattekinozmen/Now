<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\Leave;
use App\Models\User;

class LeavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LogisticsPermission::VIEW);
    }

    public function view(User $user, Leave $leave): bool
    {
        return $user->can(LogisticsPermission::VIEW)
            && (int) $user->tenant_id === (int) $leave->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::LEAVES_WRITE);
    }

    public function update(User $user, Leave $leave): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::LEAVES_WRITE)
            && (int) $user->tenant_id === (int) $leave->tenant_id;
    }

    /** Maker-Checker: only admin can approve */
    public function approve(User $user, Leave $leave): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $leave->tenant_id
            && $leave->status->isPending();
    }

    public function delete(User $user, Leave $leave): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $leave->tenant_id;
    }
}
