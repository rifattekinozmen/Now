<?php

use App\Authorization\LogisticsPermission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Lazy, Title('Team')] class extends Component
{
    public ?string $flashMessage = null;

    public function mount(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            abort(403);
        }
    }

    /**
     * @return array{total:int, admins:int, viewers:int, noAccess:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $users = $this->teamUsers;

        return [
            'total'    => $users->count(),
            'admins'   => $users->filter(fn ($u) => $u->can(LogisticsPermission::ADMIN))->count(),
            'viewers'  => $users->filter(fn ($u) => ! $u->can(LogisticsPermission::ADMIN) && $u->can(LogisticsPermission::VIEW))->count(),
            'noAccess' => $users->filter(fn ($u) => ! $u->can(LogisticsPermission::VIEW))->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    #[Computed]
    public function teamUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with('roles', 'permissions')
            ->orderBy('name')
            ->get();
    }

    public function makeAdmin(int $userId): void
    {
        if ($userId === Auth::id()) {
            $this->flashMessage = __('You cannot change your own role.');

            return;
        }

        $user = $this->findUserInTenant($userId);

        RolesAndPermissionsSeeder::ensureDefaults();

        $role = Role::query()->where('name', RolesAndPermissionsSeeder::ROLE_TENANT_USER)->firstOrFail();
        $user->syncRoles([$role]);
        $user->syncPermissions([]);

        $this->flashMessage = __(':name is now an admin.', ['name' => $user->name]);
        unset($this->teamUsers);
    }

    public function makeViewer(int $userId): void
    {
        if ($userId === Auth::id()) {
            $this->flashMessage = __('You cannot change your own role.');

            return;
        }

        $user = $this->findUserInTenant($userId);

        RolesAndPermissionsSeeder::ensureDefaults();

        $role = Role::query()->where('name', RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER)->firstOrFail();
        $user->syncRoles([$role]);
        $user->syncPermissions([]);

        $this->flashMessage = __(':name is now a viewer.', ['name' => $user->name]);
        unset($this->teamUsers);
    }

    public function removeAccess(int $userId): void
    {
        if ($userId === Auth::id()) {
            $this->flashMessage = __('You cannot change your own role.');

            return;
        }

        $user = $this->findUserInTenant($userId);
        $user->syncRoles([]);
        $user->syncPermissions([]);

        $this->flashMessage = __(':name\'s access has been removed.', ['name' => $user->name]);
        unset($this->teamUsers);
    }

    private function findUserInTenant(int $userId): User
    {
        $user = User::query()
            ->where('id', $userId)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        return $user;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Team')">
        <x-slot name="actions">
            <flux:button :href="route('profile.edit')" variant="ghost" wire:navigate>{{ __('Settings') }}</flux:button>
        </x-slot>
        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Users registered under your tenant account.') }}
        </flux:text>
    </x-admin.page-header>

    @if ($flashMessage)
        <flux:callout variant="success" icon="check-circle">{{ $flashMessage }}</flux:callout>
    @endif

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total users') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Admins') }}</flux:text>
            <flux:heading size="lg" class="text-indigo-600 dark:text-indigo-400">{{ $this->kpiStats['admins'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Viewers') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600 dark:text-blue-400">{{ $this->kpiStats['viewers'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('No access') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['noAccess'] > 0 ? 'text-yellow-500' : '' }}">{{ $this->kpiStats['noAccess'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Users table --}}
    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-3 ps-4 pe-4 text-start font-medium">{{ __('Name') }}</th>
                        <th class="py-3 pe-4 font-medium">{{ __('Email') }}</th>
                        <th class="py-3 pe-4 font-medium">{{ __('Role') }}</th>
                        <th class="py-3 pe-4 font-medium">{{ __('Linked employee') }}</th>
                        <th class="py-3 pe-4 font-medium">{{ __('Joined') }}</th>
                        <th class="py-3 pe-4 font-medium text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->teamUsers as $user)
                        @php
                            $isSelf   = $user->id === auth()->id();
                            $isAdmin  = $user->can(LogisticsPermission::ADMIN);
                            $isViewer = !$isAdmin && $user->can(LogisticsPermission::VIEW);
                        @endphp
                        <tr class="{{ $isSelf ? 'bg-zinc-50 dark:bg-zinc-800/30' : '' }}">
                            <td class="py-3 ps-4 pe-4 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $user->name }}
                                @if ($isSelf)
                                    <flux:badge size="sm" color="zinc" class="ms-1">{{ __('You') }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 pe-4 text-zinc-600 dark:text-zinc-400">{{ $user->email }}</td>
                            <td class="py-3 pe-4">
                                @if ($isAdmin)
                                    <flux:badge color="indigo" size="sm">{{ __('Admin') }}</flux:badge>
                                @elseif ($isViewer)
                                    <flux:badge color="blue" size="sm">{{ __('Viewer') }}</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm">{{ __('No access') }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 pe-4 text-zinc-500">
                                @if ($user->employee_id)
                                    <flux:button size="sm" variant="ghost" :href="route('admin.employees.show', $user->employee_id)" wire:navigate>
                                        #{{ $user->employee_id }}
                                    </flux:button>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 pe-4 text-zinc-500">{{ $user->created_at->format('d M Y') }}</td>
                            <td class="py-3 pe-4 text-end">
                                @unless ($isSelf)
                                    <flux:dropdown>
                                        <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">{{ __('Change role') }}</flux:button>
                                        <flux:menu>
                                            @unless ($isAdmin)
                                                <flux:menu.item wire:click="makeAdmin({{ $user->id }})" icon="shield-check">{{ __('Make admin') }}</flux:menu.item>
                                            @endunless
                                            @unless ($isViewer)
                                                <flux:menu.item wire:click="makeViewer({{ $user->id }})" icon="eye">{{ __('Make viewer') }}</flux:menu.item>
                                            @endunless
                                            @unless (!$isAdmin && !$isViewer)
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="removeAccess({{ $user->id }})" icon="x-mark" variant="danger"
                                                    wire:confirm="{{ __('Remove access for :name?', ['name' => $user->name]) }}">
                                                    {{ __('Remove access') }}
                                                </flux:menu.item>
                                            @endunless
                                        </flux:menu>
                                    </flux:dropdown>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-zinc-500">{{ __('No users found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:callout variant="info" icon="information-circle">
        <flux:callout.text class="text-sm">
            {{ __('Role changes take effect immediately. Admins have full access; viewers can read all data but cannot make changes.') }}
        </flux:callout.text>
    </flux:callout>
</div>
