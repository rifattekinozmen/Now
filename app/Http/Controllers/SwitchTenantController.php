<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SwitchTenantController extends Controller
{
    public function __invoke(Tenant $tenant): RedirectResponse
    {
        $user = Auth::user();

        // Only allow switching to a tenant the user belongs to and that is active
        abort_unless(
            $user->tenants()->where('tenant_id', $tenant->id)->exists() && ! $tenant->isArchived(),
            403
        );

        $user->update(['active_tenant_id' => $tenant->id]);

        // Clear sidebar + stats caches for all locales
        foreach (['tr', 'en'] as $lang) {
            cache()->forget('sidebar-menu-v5-'.$user->id.'-'.$lang);
            cache()->forget('orders-stats-'.$user->active_tenant_id.'-'.$lang);
        }

        session()->flash('status', __('Switched to :name.', ['name' => $tenant->name]));

        return redirect()->route('dashboard');
    }
}
