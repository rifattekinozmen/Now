<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return (int) $user->tenant_id === (int) $shipment->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return (int) $user->tenant_id === (int) $shipment->tenant_id;
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return (int) $user->tenant_id === (int) $shipment->tenant_id;
    }
}
