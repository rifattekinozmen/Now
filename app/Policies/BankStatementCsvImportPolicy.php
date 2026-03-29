<?php

namespace App\Policies;

use App\Models\BankStatementCsvImport;
use App\Models\User;

class BankStatementCsvImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, BankStatementCsvImport $import): bool
    {
        return (int) $user->tenant_id === (int) $import->tenant_id;
    }
}
