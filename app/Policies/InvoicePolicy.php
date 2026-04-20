<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return (int) $user->tenant_id === (int) $invoice->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return (int) $user->tenant_id === (int) $invoice->tenant_id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return (int) $user->tenant_id === (int) $invoice->tenant_id;
    }
}
