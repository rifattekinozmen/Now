<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleFine;

class VehicleFinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, VehicleFine $vehicleFine): bool
    {
        return (int) $user->tenant_id === (int) $vehicleFine->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, VehicleFine $vehicleFine): bool
    {
        return (int) $user->tenant_id === (int) $vehicleFine->tenant_id;
    }

    public function delete(User $user, VehicleFine $vehicleFine): bool
    {
        return (int) $user->tenant_id === (int) $vehicleFine->tenant_id;
    }
}
