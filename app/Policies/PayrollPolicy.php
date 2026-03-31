<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LogisticsPermission::VIEW);
    }

    public function view(User $user, Payroll $payroll): bool
    {
        return $user->can(LogisticsPermission::VIEW)
            && (int) $user->tenant_id === (int) $payroll->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::PAYROLL_WRITE);
    }

    public function update(User $user, Payroll $payroll): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::PAYROLL_WRITE)
            && (int) $user->tenant_id === (int) $payroll->tenant_id;
    }

    /** Maker-Checker: only admin approves / marks paid */
    public function approve(User $user, Payroll $payroll): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $payroll->tenant_id
            && ! $payroll->status->isPaid();
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $payroll->tenant_id
            && $payroll->status->isDraft();
    }
}
