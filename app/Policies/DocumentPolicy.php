<?php

namespace App\Policies;

use App\Authorization\LogisticsPermission;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return LogisticsPermission::canView($user);
    }

    public function view(User $user, Document $document): bool
    {
        return LogisticsPermission::canView($user)
            && (int) $user->tenant_id === (int) $document->tenant_id;
    }

    public function create(User $user): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::DOCUMENTS_WRITE);
    }

    public function update(User $user, Document $document): bool
    {
        return LogisticsPermission::canWrite($user, LogisticsPermission::DOCUMENTS_WRITE)
            && (int) $user->tenant_id === (int) $document->tenant_id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can(LogisticsPermission::ADMIN)
            && (int) $user->tenant_id === (int) $document->tenant_id;
    }
}
