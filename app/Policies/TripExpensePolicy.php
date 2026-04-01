<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\TripExpense;
use App\Models\User;

class TripExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, TripExpense $tripExpense): bool
    {
        return $this->viewAny($user)
            && $user->tenant_id === $tripExpense->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::TRIP_EXPENSES_WRITE);
    }

    public function update(User $user, TripExpense $tripExpense): bool
    {
        return $this->create($user)
            && $user->tenant_id === $tripExpense->tenant_id;
    }

    public function delete(User $user, TripExpense $tripExpense): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $tripExpense->tenant_id;
    }

    public function restore(User $user, TripExpense $tripExpense): bool
    {
        return false;
    }

    public function forceDelete(User $user, TripExpense $tripExpense): bool
    {
        return false;
    }
}
