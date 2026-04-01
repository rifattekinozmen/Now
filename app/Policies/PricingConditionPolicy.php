<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\PricingCondition;
use App\Models\User;

class PricingConditionPolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, PricingCondition $pricingCondition): bool
    {
        return $this->viewAny($user)
            && $user->tenant_id === $pricingCondition->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::PRICING_CONDITIONS_WRITE);
    }

    public function update(User $user, PricingCondition $pricingCondition): bool
    {
        return $this->create($user)
            && $user->tenant_id === $pricingCondition->tenant_id;
    }

    public function delete(User $user, PricingCondition $pricingCondition): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && $user->tenant_id === $pricingCondition->tenant_id;
    }

    public function restore(User $user, PricingCondition $pricingCondition): bool
    {
        return false;
    }

    public function forceDelete(User $user, PricingCondition $pricingCondition): bool
    {
        return false;
    }
}
