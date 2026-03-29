<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\ChartAccount;
use App\Models\User;

class ChartAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && ($user->can(LogisticsPermission::ADMIN) || $user->can(LogisticsPermission::VIEW));
    }

    public function view(User $user, ChartAccount $chartAccount): bool
    {
        return (int) $user->tenant_id === (int) $chartAccount->tenant_id
            && ($user->can(LogisticsPermission::ADMIN) || $user->can(LogisticsPermission::VIEW));
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && LogisticsPermission::canWrite($user, LogisticsPermission::FINANCE_WRITE);
    }

    public function update(User $user, ChartAccount $chartAccount): bool
    {
        return (int) $user->tenant_id === (int) $chartAccount->tenant_id
            && LogisticsPermission::canWrite($user, LogisticsPermission::FINANCE_WRITE);
    }

    public function delete(User $user, ChartAccount $chartAccount): bool
    {
        return $this->update($user, $chartAccount);
    }
}
