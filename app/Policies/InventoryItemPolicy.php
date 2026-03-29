<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return (int) $user->tenant_id === (int) $inventoryItem->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return (int) $user->tenant_id === (int) $inventoryItem->tenant_id;
    }

    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        return (int) $user->tenant_id === (int) $inventoryItem->tenant_id;
    }
}
