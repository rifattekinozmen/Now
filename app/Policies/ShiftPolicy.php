<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, Shift $shift): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $shift->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::SHIFTS_WRITE);
    }

    public function update(User $user, Shift $shift): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::SHIFTS_WRITE)
            && (int) $user->tenant_id === (int) $shift->tenant_id;
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $shift->tenant_id;
    }
}
