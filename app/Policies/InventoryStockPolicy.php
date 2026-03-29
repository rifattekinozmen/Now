<?php

namespace App\Policies;

use App\Models\InventoryStock;
use App\Models\User;

class InventoryStockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, InventoryStock $inventoryStock): bool
    {
        return (int) $user->tenant_id === (int) $inventoryStock->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, InventoryStock $inventoryStock): bool
    {
        return (int) $user->tenant_id === (int) $inventoryStock->tenant_id;
    }

    public function delete(User $user, InventoryStock $inventoryStock): bool
    {
        return (int) $user->tenant_id === (int) $inventoryStock->tenant_id;
    }
}
