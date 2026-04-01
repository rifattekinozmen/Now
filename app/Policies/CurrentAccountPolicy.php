<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\CurrentAccount;
use App\Models\User;

class CurrentAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, CurrentAccount $currentAccount): bool
    {
        return $this->viewAny($user)
            && $user->tenant_id === $currentAccount->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::CURRENT_ACCOUNTS_WRITE);
    }

    public function update(User $user, CurrentAccount $currentAccount): bool
    {
        return $this->create($user)
            && $user->tenant_id === $currentAccount->tenant_id;
    }

    public function delete(User $user, CurrentAccount $currentAccount): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $currentAccount->tenant_id;
    }

    public function restore(User $user, CurrentAccount $currentAccount): bool
    {
        return false;
    }

    public function forceDelete(User $user, CurrentAccount $currentAccount): bool
    {
        return false;
    }
}
