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
        if (! LogisticsPermission::canView($user)) {
            return false;
        }

        $effectiveTenantId = $user->active_tenant_id ?? $user->tenant_id;

        if ($effectiveTenantId === null) {
            return false;
        }

        return (int) $effectiveTenantId === (int) $deliveryImport->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN);
    }

    public function delete(User $user, DeliveryImport $deliveryImport): bool
    {
        if (! $user->can(LogisticsPermission::ADMIN)) {
            return false;
        }

        $effectiveTenantId = $user->active_tenant_id ?? $user->tenant_id;

        if ($effectiveTenantId === null) {
            return false;
        }

        return (int) $effectiveTenantId === (int) $deliveryImport->tenant_id;
    }
}
