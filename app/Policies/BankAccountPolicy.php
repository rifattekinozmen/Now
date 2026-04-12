<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $bankAccount->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN);
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $bankAccount->tenant_id;
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $bankAccount->tenant_id;
    }
}
