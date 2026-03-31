<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\PersonnelAttendance;
use App\Models\User;

class PersonnelAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LogisticsPermission::VIEW);
    }

    public function view(User $user, PersonnelAttendance $personnelAttendance): bool
    {
        return $user->can(LogisticsPermission::VIEW)
            && (int) $user->tenant_id === (int) $personnelAttendance->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::EMPLOYEES_WRITE);
    }

    public function update(User $user, PersonnelAttendance $personnelAttendance): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::EMPLOYEES_WRITE)
            && (int) $user->tenant_id === (int) $personnelAttendance->tenant_id;
    }

    /** Maker-Checker: only admin can approve */
    public function approve(User $user, PersonnelAttendance $personnelAttendance): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $personnelAttendance->tenant_id
            && ! $personnelAttendance->isApproved();
    }

    public function delete(User $user, PersonnelAttendance $personnelAttendance): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $personnelAttendance->tenant_id;
    }
}
