<?php

use App\Authorization\LogisticsPermission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Companies')] class extends Component
{
    // ── Create ──
    public string $newName = '';

    // ── Edit ──
    public ?int $editingId = null;
    public string $editName = '';

    // ── Add user to tenant ──
    public ?int $addUserTenantId = null;
    public string $addUserEmail  = '';
    public string $addUserName   = '';
    public bool   $addUserIsNew  = false;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::SUPER_ADMIN), 403);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Tenant> */
    #[Computed]
    public function tenants(): \Illuminate\Database\Eloquent\Collection
    {
        return Tenant::query()
            ->withCount('users')
            ->orderByRaw('archived_at IS NOT NULL, name')
            ->get();
    }

    public function createCompany(): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::SUPER_ADMIN), 403);

        $this->validate(['newName' => ['required', 'string', 'max:200']]);

        $tenant = Tenant::create([
            'name' => $this->newName,
            'slug' => Str::slug($this->newName),
        ]);

        // Super-admin is automatically added to the new company
        $user = Auth::user();
        $user->tenants()->syncWithoutDetaching([$tenant->id]);

        $this->newName = '';
        unset($this->tenants);

        session()->flash('newTenantId', $tenant->id);
        session()->flash('newTenantName', $tenant->name);
    }

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
        $this->editName = Tenant::findOrFail($id)->name;
    }

    public function saveEdit(): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::SUPER_ADMIN), 403);

        $this->validate(['editName' => ['required', 'string', 'max:200']]);

        $tenant = Tenant::findOrFail($this->editingId);
        $tenant->update(['name' => $this->editName]);

        $this->editingId = null;
        $this->editName = '';
        unset($this->tenants);
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editName = '';
    }

    public function archive(int $id): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::SUPER_ADMIN), 403);

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['archived_at' => now()]);

        // Reset active_tenant_id for any user whose active tenant is now archived
        User::query()
            ->where('active_tenant_id', $id)
            ->where('tenant_id', '!=', $id)
            ->update(['active_tenant_id' => \Illuminate\Support\Facades\DB::raw('tenant_id')]);

        User::query()
            ->where('active_tenant_id', $id)
            ->where('tenant_id', $id)
            ->update(['active_tenant_id' => null]);

        unset($this->tenants);
    }

    public function restore(int $id): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::SUPER_ADMIN), 403);

        Tenant::findOrFail($id)->update(['archived_at' => null]);
        unset($this->tenants);
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::SUPER_ADMIN), 403);

        $tenant = Tenant::withCount('users')->findOrFail($id);

        abort_unless($tenant->isArchived(), 403, __('Archive the company first before deleting.'));
        abort_unless($tenant->users_count === 0, 422, __('Remove all users before deleting the company.'));

        $tenant->delete();
        unset($this->tenants);

        session()->flash('status', __(':name has been permanently deleted.', ['name' => $tenant->name]));
    }

    public function startAddUser(int $tenantId): void
    {
        $this->addUserTenantId = $tenantId;
        $this->addUserEmail    = '';
        $this->addUserName     = '';
        $this->addUserIsNew    = false;
    }

    public function cancelAddUser(): void
    {
        $this->addUserTenantId = null;
        $this->addUserEmail    = '';
        $this->addUserName     = '';
        $this->addUserIsNew    = false;
    }

    public function addUser(): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::SUPER_ADMIN), 403);

        $this->validate(['addUserEmail' => ['required', 'email']]);

        $user = User::query()->where('email', $this->addUserEmail)->first();

        if (! $user && ! $this->addUserIsNew) {
            $this->addUserIsNew = true;

            return;
        }

        if (! $user) {
            $this->validate(['addUserName' => ['required', 'string', 'max:200']]);

            $user = User::create([
                'name'      => $this->addUserName,
                'email'     => $this->addUserEmail,
                'password'  => Hash::make(Str::random(32)),
                'tenant_id' => $this->addUserTenantId,
            ]);

            RolesAndPermissionsSeeder::ensureDefaults();
            $user->assignRole(RolesAndPermissionsSeeder::ROLE_TENANT_USER);
            Password::sendResetLink(['email' => $user->email]);
        }

        $user->tenants()->syncWithoutDetaching([$this->addUserTenantId]);

        if (! $user->active_tenant_id) {
            $user->update(['active_tenant_id' => $this->addUserTenantId]);
        }

        $this->addUserTenantId = null;
        $this->addUserEmail    = '';
        $this->addUserName     = '';
        $this->addUserIsNew    = false;
        unset($this->tenants);

        session()->flash('status', __('User added successfully.'));
    }

    public function removeUser(int $userId, int $tenantId): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::SUPER_ADMIN), 403);

        $user = User::findOrFail($userId);

        // Don't remove the user's primary tenant
        if ($user->tenant_id === $tenantId) {
            session()->flash('error', __('Cannot remove a user from their primary company. Change their primary company first.'));

            return;
        }

        $user->tenants()->detach($tenantId);

        if ($user->active_tenant_id === $tenantId) {
            $user->update(['active_tenant_id' => $user->tenant_id]);
        }

        unset($this->tenants);
    }
}; ?>

<x-settings.layout
    :heading="__('Companies')"
    :subheading="__('Manage the companies on this platform.')"
>
    @if (session()->has('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    @if (session()->has('error'))
        <flux:callout variant="danger" icon="x-circle" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    {{-- ── Next steps callout after new company ── --}}
    @if (session()->has('newTenantName'))
        <flux:callout variant="success" icon="building-office" class="mb-4">
            <flux:callout.heading>{{ __('":name" created.', ['name' => session('newTenantName')]) }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Next steps:') }}
                <ol class="mt-1 list-decimal pl-4 space-y-0.5 text-sm">
                    <li>{{ __('Switch to the new company using the profile menu (bottom-left).') }}</li>
                    <li>{{ __('Go to Team → add the first admin user.') }}</li>
                    <li>{{ __('Go to Settings → Company → fill in logo, address and tax ID.') }}</li>
                </ol>
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- ── KPI bar ── --}}
    @php
        $active   = $this->tenants->whereNull('archived_at')->count();
        $archived = $this->tenants->whereNotNull('archived_at')->count();
    @endphp
    <div class="mb-5 flex gap-4">
        <div class="flex-1 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $active }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</p>
        </div>
        <div class="flex-1 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $archived }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Archived') }}</p>
        </div>
    </div>

    {{-- ── Create new company ── --}}
    <div class="mb-6 rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
        <p class="mb-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('New company') }}</p>
        <div class="flex gap-2">
            <flux:input
                wire:model="newName"
                wire:keydown.enter="createCompany"
                placeholder="{{ __('Company name') }}"
                class="flex-1"
            />
            <flux:button wire:click="createCompany" variant="primary" icon="plus">
                {{ __('Create') }}
            </flux:button>
        </div>
        @error('newName') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
        <flux:description class="mt-2">
            {{ __('After creating, switch to the company and add users from the Team page.') }}
        </flux:description>
    </div>

    {{-- ── Company list ── --}}
    <div class="space-y-4">
        @forelse($this->tenants as $tenant)
            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800"
                 style="{{ $tenant->isArchived() ? 'opacity:0.6' : '' }}">

                {{-- Header ─────────────────────────────────────── --}}
                <div class="flex items-center justify-between gap-4 p-4">

                    {{-- Left: name / edit form — avoid @if around Flux components --}}
                    <div class="min-w-0">
                        <div @class(['hidden' => $editingId !== $tenant->id]) class="flex items-center gap-2">
                            <flux:input wire:model="editName" wire:keydown.enter="saveEdit" class="w-48" />
                            <flux:button wire:click="saveEdit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
                            <flux:button wire:click="cancelEdit" size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
                        </div>
                        <div @class(['hidden' => $editingId === $tenant->id])>
                            <p class="font-semibold text-zinc-900 dark:text-white">{{ $tenant->name }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $tenant->users_count }} {{ __('users') }}
                                · {{ __('Created') }} {{ $tenant->created_at->format('M Y') }}
                                {{ $tenant->isArchived() ? '· ' . __('Archived') . ' ' . $tenant->archived_at->format('M Y') : '' }}
                            </p>
                        </div>
                    </div>

                    {{-- Row action dropdown — flat @unless/@if (no nesting) avoids Livewire component-stack bug --}}
                    <flux:dropdown position="bottom" align="end">
                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                        <flux:menu>
                            @unless($tenant->isArchived())
                                <flux:menu.item wire:click="startEdit({{ $tenant->id }})" icon="pencil">{{ __('Rename') }}</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="archive({{ $tenant->id }})" wire:confirm="{{ __('Archive :name? Users will be redirected to their primary company.', ['name' => $tenant->name]) }}" icon="archive-box" variant="danger">{{ __('Archive') }}</flux:menu.item>
                            @endunless
                            @if($tenant->isArchived())
                                <flux:menu.item wire:click="restore({{ $tenant->id }})" icon="arrow-uturn-left">{{ __('Restore') }}</flux:menu.item>
                            @endif
                            @if($tenant->isArchived() && $tenant->users_count === 0)
                                <flux:menu.separator />
                                <flux:menu.item wire:click="delete({{ $tenant->id }})" wire:confirm="{{ __('Permanently delete :name? This cannot be undone.', ['name' => $tenant->name]) }}" icon="trash" variant="danger">{{ __('Delete') }}</flux:menu.item>
                            @endif
                        </flux:menu>
                    </flux:dropdown>
                </div>

                {{-- User section — only for active companies (no @if around Flux components) --}}
                <div @class(['hidden' => $tenant->isArchived()])>
                    <flux:separator />
                    <div class="p-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Users') }}</p>

                        @foreach($tenant->users()->orderBy('name')->get() as $u)
                            <div class="flex items-center justify-between py-1">
                                <div class="flex items-center gap-2">
                                    <flux:avatar :name="$u->name" :initials="$u->initials()" size="xs" />
                                    <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ $u->name }}</span>
                                    <span @class(['hidden' => $u->tenant_id !== $tenant->id]) class="text-xs text-zinc-400">({{ __('primary') }})</span>
                                </div>
                                <span @class(['hidden' => $u->tenant_id === $tenant->id])>
                                    <flux:button
                                        wire:click="removeUser({{ $u->id }}, {{ $tenant->id }})"
                                        wire:confirm="{{ __('Remove :name from :company?', ['name' => $u->name, 'company' => $tenant->name]) }}"
                                        size="xs" variant="ghost" icon="x-mark"
                                        class="text-zinc-400 hover:text-red-500"
                                    />
                                </span>
                            </div>
                        @endforeach

                        @if($tenant->users()->count() === 0)
                            <p class="text-sm text-zinc-400">{{ __('No users yet.') }}</p>
                        @endif

                        {{-- Add user form — shown/hidden via Alpine @class binding --}}
                        <div @class(['hidden' => $addUserTenantId !== $tenant->id]) class="mt-3 space-y-2">
                            <div class="flex items-start gap-2">
                                <div class="flex-1">
                                    <flux:input wire:model="addUserEmail" wire:keydown.enter="addUser" type="email" placeholder="{{ __('user@example.com') }}" size="sm" />
                                    @error('addUserEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                @if (! ($addUserIsNew && $addUserTenantId === $tenant->id))
                                    <flux:button wire:click="addUser" size="sm" variant="primary">{{ __('Add') }}</flux:button>
                                @endif
                                <flux:button wire:click="cancelAddUser" size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
                            </div>
                            @if ($addUserIsNew && $addUserTenantId === $tenant->id)
                                <flux:callout variant="warning" icon="user-plus">{{ __('No account found. Enter a name to create a new user and send an invitation email.') }}</flux:callout>
                                <div class="flex items-start gap-2">
                                    <div class="flex-1">
                                        <flux:input wire:model="addUserName" wire:keydown.enter="addUser" placeholder="{{ __('Full name') }}" size="sm" />
                                        @error('addUserName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <flux:button wire:click="addUser" size="sm" variant="primary" icon="paper-airplane">{{ __('Create & Invite') }}</flux:button>
                                </div>
                            @endif
                        </div>
                        <div @class(['hidden' => $addUserTenantId === $tenant->id])>
                            <flux:button wire:click="startAddUser({{ $tenant->id }})" size="sm" variant="ghost" icon="user-plus" class="mt-2">
                                {{ __('Add user by email') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-zinc-400">{{ __('No companies yet.') }}</p>
        @endforelse
    </div>
</x-settings.layout>
