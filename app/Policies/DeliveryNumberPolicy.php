<?php

namespace App\Policies;

use App\Models\DeliveryNumber;
use App\Models\User;

class DeliveryNumberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, DeliveryNumber $deliveryNumber): bool
    {
        return (int) $user->tenant_id === (int) $deliveryNumber->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, DeliveryNumber $deliveryNumber): bool
    {
        return (int) $user->tenant_id === (int) $deliveryNumber->tenant_id;
    }

    public function delete(User $user, DeliveryNumber $deliveryNumber): bool
    {
        return (int) $user->tenant_id === (int) $deliveryNumber->tenant_id;
    }
}
