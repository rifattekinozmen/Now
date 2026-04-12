<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $workOrder->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN);
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $workOrder->tenant_id;
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $workOrder->tenant_id;
    }
}
