<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\User;
use App\Models\VehicleTyre;

class VehicleTyrePolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, VehicleTyre $vehicleTyre): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $vehicleTyre->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN);
    }

    public function update(User $user, VehicleTyre $vehicleTyre): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $vehicleTyre->tenant_id;
    }

    public function delete(User $user, VehicleTyre $vehicleTyre): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $vehicleTyre->tenant_id;
    }
}
