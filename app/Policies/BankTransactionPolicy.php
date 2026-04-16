<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\BankTransaction;
use App\Models\User;

class BankTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            || $user->can(LogisticsPermission::VIEW)
            || $user->can(LogisticsPermission::FINANCE_WRITE);
    }

    public function view(User $user, BankTransaction $transaction): bool
    {
        return $this->viewAny($user)
            && $user->tenant_id === $transaction->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::FINANCE_WRITE);
    }

    public function update(User $user, BankTransaction $transaction): bool
    {
        return $this->create($user)
            && $user->tenant_id === $transaction->tenant_id;
    }

    public function delete(User $user, BankTransaction $transaction): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $transaction->tenant_id
            && ! $transaction->is_reconciled;
    }

    public function reconcile(User $user, BankTransaction $transaction): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $transaction->tenant_id;
    }

    public function restore(User $user, BankTransaction $transaction): bool
    {
        return false;
    }

    public function forceDelete(User $user, BankTransaction $transaction): bool
    {
        return false;
    }
}
