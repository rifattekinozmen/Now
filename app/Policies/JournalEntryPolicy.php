<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && ($user->can(LogisticsPermission::ADMIN) || $user->can(LogisticsPermission::VIEW));
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return (int) $user->tenant_id === (int) $journalEntry->tenant_id
            && ($user->can(LogisticsPermission::ADMIN) || $user->can(LogisticsPermission::VIEW));
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && LogisticsPermission::canWrite($user, LogisticsPermission::FINANCE_WRITE);
    }
}
