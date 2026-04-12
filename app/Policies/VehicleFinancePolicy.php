<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\User;
use App\Models\VehicleFinance;

class VehicleFinancePolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, VehicleFinance $vehicleFinance): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $vehicleFinance->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN);
    }

    public function update(User $user, VehicleFinance $vehicleFinance): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $vehicleFinance->tenant_id;
    }

    public function delete(User $user, VehicleFinance $vehicleFinance): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $vehicleFinance->tenant_id;
    }
}
