<?php

namespace App\Policies;

use App\Models\FuelPrice;
use App\Models\User;

class FuelPricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, FuelPrice $fuelPrice): bool
    {
        return (int) $user->tenant_id === (int) $fuelPrice->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, FuelPrice $fuelPrice): bool
    {
        return (int) $user->tenant_id === (int) $fuelPrice->tenant_id;
    }

    public function delete(User $user, FuelPrice $fuelPrice): bool
    {
        return (int) $user->tenant_id === (int) $fuelPrice->tenant_id;
    }
}
