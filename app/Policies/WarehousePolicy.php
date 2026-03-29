<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return (int) $user->tenant_id === (int) $warehouse->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return (int) $user->tenant_id === (int) $warehouse->tenant_id;
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return (int) $user->tenant_id === (int) $warehouse->tenant_id;
    }
}
