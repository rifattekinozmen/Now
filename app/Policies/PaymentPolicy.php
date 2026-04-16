<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            || $user->can(LogisticsPermission::VIEW)
            || $user->can(LogisticsPermission::VOUCHERS_WRITE);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user)
            && $user->tenant_id === $payment->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::VOUCHERS_WRITE);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $this->create($user)
            && $user->tenant_id === $payment->tenant_id
            && $payment->status->isPending();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $payment->tenant_id
            && $payment->status->isPending();
    }

    /** Maker-Checker: only logistics.admin can approve */
    public function approve(User $user, Payment $payment): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $payment->tenant_id
            && $payment->status->isPending();
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
