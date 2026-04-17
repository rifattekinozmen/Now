<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\DeliveryImport;
use App\Models\User;

class DeliveryImportPolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, DeliveryImport $deliveryImport): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $deliveryImport->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN);
    }

    public function delete(User $user, DeliveryImport $deliveryImport): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $deliveryImport->tenant_id;
    }
}
