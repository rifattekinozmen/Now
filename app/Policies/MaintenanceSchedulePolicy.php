<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\MaintenanceSchedule;
use App\Models\User;

class MaintenanceSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $maintenanceSchedule->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN);
    }

    public function update(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $maintenanceSchedule->tenant_id;
    }

    public function delete(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $maintenanceSchedule->tenant_id;
    }
}
