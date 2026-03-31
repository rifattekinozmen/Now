<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\User;
use App\Models\Voucher;

class VoucherPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            || $user->can(LogisticsPermission::VIEW)
            || $user->can(LogisticsPermission::VOUCHERS_WRITE);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Voucher $voucher): bool
    {
        return $this->viewAny($user)
            && $user->tenant_id === $voucher->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::VOUCHERS_WRITE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Voucher $voucher): bool
    {
        return $this->create($user)
            && $user->tenant_id === $voucher->tenant_id
            && $voucher->status->isPending();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Voucher $voucher): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $voucher->tenant_id
            && $voucher->status->isPending();
    }

    /** Maker-Checker: only logistics.admin can approve */
    public function approve(User $user, Voucher $voucher): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $voucher->tenant_id
            && $voucher->status->isPending();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Voucher $voucher): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Voucher $voucher): bool
    {
        return false;
    }
}
