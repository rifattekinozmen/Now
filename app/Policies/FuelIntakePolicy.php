<?php

namespace App\Policies;

use App\Models\FuelIntake;
use App\Models\User;

class FuelIntakePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, FuelIntake $fuelIntake): bool
    {
        return (int) $user->tenant_id === (int) $fuelIntake->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, FuelIntake $fuelIntake): bool
    {
        return (int) $user->tenant_id === (int) $fuelIntake->tenant_id;
    }

    public function delete(User $user, FuelIntake $fuelIntake): bool
    {
        return (int) $user->tenant_id === (int) $fuelIntake->tenant_id;
    }
}
