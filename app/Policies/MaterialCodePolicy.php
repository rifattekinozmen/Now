<?php

namespace App\Policies;

use App\Models\MaterialCode;
use App\Models\User;

class MaterialCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, MaterialCode $materialCode): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, MaterialCode $materialCode): bool
    {
        return $user->tenant_id !== null;
    }

    public function delete(User $user, MaterialCode $materialCode): bool
    {
        return $user->tenant_id !== null;
    }
}
