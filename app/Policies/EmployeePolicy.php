<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Employee $employee): bool
    {
        return (int) $user->tenant_id === (int) $employee->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Employee $employee): bool
    {
        return (int) $user->tenant_id === (int) $employee->tenant_id;
    }

    public function delete(User $user, Employee $employee): bool
    {
        return (int) $user->tenant_id === (int) $employee->tenant_id;
    }
}
