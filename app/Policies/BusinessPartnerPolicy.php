<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\BusinessPartner;
use App\Models\User;

class BusinessPartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, BusinessPartner $partner): bool
    {
        return (int) $user->tenant_id === (int) $partner->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(LogisticsPermission::ADMIN);
    }

    public function update(User $user, BusinessPartner $partner): bool
    {
        return $user->hasPermissionTo(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $partner->tenant_id;
    }

    public function delete(User $user, BusinessPartner $partner): bool
    {
        return $user->hasPermissionTo(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $partner->tenant_id;
    }
}
