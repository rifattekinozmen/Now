<?php

namespace App\Policies;

use App\Models\CbamReport;
use App\Models\User;

class CbamReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, CbamReport $cbamReport): bool
    {
        return (int) $user->tenant_id === (int) $cbamReport->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, CbamReport $cbamReport): bool
    {
        return (int) $user->tenant_id === (int) $cbamReport->tenant_id;
    }

    public function delete(User $user, CbamReport $cbamReport): bool
    {
        return (int) $user->tenant_id === (int) $cbamReport->tenant_id;
    }
}
