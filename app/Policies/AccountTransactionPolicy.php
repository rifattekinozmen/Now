<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\AccountTransaction;
use App\Models\User;

class AccountTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, AccountTransaction $accountTransaction): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $accountTransaction->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN);
    }

    public function update(User $user, AccountTransaction $accountTransaction): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $accountTransaction->tenant_id;
    }

    public function delete(User $user, AccountTransaction $accountTransaction): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $accountTransaction->tenant_id;
    }
}
