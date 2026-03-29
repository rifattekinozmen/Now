<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\FiscalOpeningBalance;
use App\Models\User;

class FiscalOpeningBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && ($user->can(LogisticsPermission::ADMIN) || $user->can(LogisticsPermission::VIEW));
    }

    public function view(User $user, FiscalOpeningBalance $fiscalOpeningBalance): bool
    {
        return (int) $user->tenant_id === (int) $fiscalOpeningBalance->tenant_id
            && ($user->can(LogisticsPermission::ADMIN) || $user->can(LogisticsPermission::VIEW));
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && LogisticsPermission::canWrite($user, LogisticsPermission::FINANCE_WRITE);
    }

    public function update(User $user, FiscalOpeningBalance $fiscalOpeningBalance): bool
    {
        return (int) $user->tenant_id === (int) $fiscalOpeningBalance->tenant_id
            && LogisticsPermission::canWrite($user, LogisticsPermission::FINANCE_WRITE);
    }

    public function delete(User $user, FiscalOpeningBalance $fiscalOpeningBalance): bool
    {
        return $this->update($user, $fiscalOpeningBalance);
    }
}
