<?php

use App\Authorization\LogisticsPermission;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\Permission\Models\Role;

new #[Title('Company profile')] class extends Component
{
    use WithFileUploads;

    public string $tab = 'profile';

    public string $companyName          = '';
    public string $companyTaxId         = '';
    public string $companyAddress       = '';
    public string $companyCity          = '';
    public string $companyPhone         = '';
    public string $companyEmail         = '';
    public string $companyWebsite       = '';
    public string $minimumFreightAmount = '';

    /** Mevcut kayıtlı logo yolu (storage/public). */
    public string $currentLogoPath = '';

    /** Yeni yüklenen geçici dosya. */
    public ?TemporaryUploadedFile $logoFile = null;

    private function activeTenantId(): int
    {
        return (int) (Auth::user()->active_tenant_id ?? Auth::user()->tenant_id);
    }

    public function mount(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            abort(403);
        }

        $tid = $this->activeTenantId();
        $tenant = Tenant::find($tid);

        $this->companyName    = $tenant?->name ?? '';
        $this->companyTaxId   = TenantSetting::get($tid, 'company_tax_id')   ?? '';
        $this->companyAddress = TenantSetting::get($tid, 'company_address')  ?? '';
        $this->companyCity    = TenantSetting::get($tid, 'company_city')     ?? '';
        $this->companyPhone   = TenantSetting::get($tid, 'company_phone')    ?? '';
        $this->companyEmail   = TenantSetting::get($tid, 'company_email')    ?? '';
        $this->companyWebsite       = TenantSetting::get($tid, 'company_website')         ?? '';
        $this->minimumFreightAmount = TenantSetting::get($tid, 'minimum_freight_amount') ?? '';
        $this->currentLogoPath      = TenantSetting::get($tid, 'company_logo')            ?? '';
    }

    public function save(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            abort(403);
        }

        $this->validate([
            'companyName'    => ['required', 'string', 'max:200'],
            'companyTaxId'   => ['nullable', 'string', 'max:20'],
            'companyAddress' => ['nullable', 'string', 'max:500'],
            'companyCity'    => ['nullable', 'string', 'max:100'],
            'companyPhone'   => ['nullable', 'string', 'max:30'],
            'companyEmail'   => ['nullable', 'email', 'max:200'],
            'companyWebsite'       => ['nullable', 'url', 'max:200'],
            'minimumFreightAmount' => ['nullable', 'numeric', 'min:0'],
            'logoFile'             => ['nullable', 'image', 'max:2048'],
        ]);

        $tid = $this->activeTenantId();

        // Logo yüklendiyse kaydet
        if ($this->logoFile) {
            if ($this->currentLogoPath) {
                Storage::disk('public')->delete($this->currentLogoPath);
            }
            $path = $this->logoFile->store('tenant-logos/'.$tid, 'public');
            TenantSetting::set($tid, 'company_logo', $path);
            $this->currentLogoPath = $path;
            $this->logoFile = null;
        }

        Tenant::where('id', $tid)->update(['name' => $this->companyName]);

        // Şirket adı değişince sidebar önbelleğini temizle
        foreach (['tr', 'en'] as $lang) {
            cache()->forget('sidebar-menu-v5-'.Auth::id().'-'.$lang);
        }

        TenantSetting::set($tid, 'company_tax_id',   filled($this->companyTaxId)   ? $this->companyTaxId   : null);
        TenantSetting::set($tid, 'company_address',  filled($this->companyAddress) ? $this->companyAddress : null);
        TenantSetting::set($tid, 'company_city',     filled($this->companyCity)    ? $this->companyCity    : null);
        TenantSetting::set($tid, 'company_phone',    filled($this->companyPhone)   ? $this->companyPhone   : null);
        TenantSetting::set($tid, 'company_email',    filled($this->companyEmail)   ? $this->companyEmail   : null);
        TenantSetting::set($tid, 'company_website',        filled($this->companyWebsite)       ? $this->companyWebsite       : null);
        TenantSetting::set($tid, 'minimum_freight_amount', filled($this->minimumFreightAmount) ? $this->minimumFreightAmount : null);

        session()->flash('status', __('Company profile saved.'));
    }

    public function removeLogo(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            abort(403);
        }

        $tid = $this->activeTenantId();

        if ($this->currentLogoPath) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }

        TenantSetting::set($tid, 'company_logo', null);
        $this->currentLogoPath = '';
        $this->logoFile = null;

        session()->flash('status', __('Logo removed.'));
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    #[Computed]
    public function teamUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $tid = $this->activeTenantId();

        return User::query()
            ->where(function ($q) use ($tid): void {
                $q->where('tenant_id', $tid)
                    ->orWhereHas('tenants', fn ($inner) => $inner->where('tenants.id', $tid));
            })
            ->with('roles')
            ->orderBy('name')
            ->distinct()
            ->get();
    }

    public function assignRole(int $userId, string $role): void
    {
        abort_unless(Auth::user()?->can(LogisticsPermission::ADMIN), 403);

        if ($userId === Auth::id()) {
            session()->flash('team_error', __('You cannot change your own role.'));

            return;
        }

        $tid = $this->activeTenantId();

        $user = User::query()
            ->where('id', $userId)
            ->where(function ($q) use ($tid): void {
                $q->where('tenant_id', $tid)
                    ->orWhereHas('tenants', fn ($inner) => $inner->where('tenants.id', $tid));
            })
            ->first();

        abort_unless($user !== null, 403);

        RolesAndPermissionsSeeder::ensureDefaults();

        $roleMap = [
            'admin'        => RolesAndPermissionsSeeder::ROLE_TENANT_USER,
            'order-clerk'  => RolesAndPermissionsSeeder::ROLE_LOGISTICS_ORDER_CLERK,
            'hr'           => RolesAndPermissionsSeeder::ROLE_LOGISTICS_HR,
            'viewer'       => RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER,
        ];

        if (isset($roleMap[$role])) {
            $roleModel = Role::query()->where('name', $roleMap[$role])->firstOrFail();
            $user->syncRoles([$roleModel]);
        } else {
            $user->syncRoles([]);
        }

        $user->syncPermissions([]);
        unset($this->teamUsers);

        session()->flash('status', __('Role updated.'));
    }
}; ?>

<x-settings.layout
    :heading="__('Company profile')"
    :subheading="__('Business information used across documents and reports.')"
>
    @if (session()->has('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    @if (session()->has('team_error'))
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">{{ session('team_error') }}</flux:callout>
    @endif

    <div class="mb-6 flex gap-1">
        <flux:button type="button" size="sm" wire:click="$set('tab', 'profile')" :variant="$tab === 'profile' ? 'primary' : 'ghost'" icon="building-office">{{ __('Profile') }}</flux:button>
        <flux:button type="button" size="sm" wire:click="$set('tab', 'team')" :variant="$tab === 'team' ? 'primary' : 'ghost'" icon="users">{{ __('Team & Roles') }}</flux:button>
    </div>

    @if ($tab === 'profile')
    <form wire:submit.prevent="save" class="space-y-5">

        {{-- ── Logo ── --}}
        <div>
            <flux:label class="mb-2 block">{{ __('Company logo') }}</flux:label>
            <flux:description class="mb-3">{{ __('Shown in the sidebar. PNG, JPG or SVG, max 2 MB.') }}</flux:description>

            <div class="flex items-center gap-4">
                {{-- Önizleme --}}
                <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    @if ($logoFile)
                        <img src="{{ $logoFile->temporaryUrl() }}" class="size-full object-contain" alt="preview" />
                    @elseif ($currentLogoPath)
                        <img src="{{ Storage::disk('public')->url($currentLogoPath) }}" class="size-full object-contain" alt="logo" />
                    @else
                        <x-app-logo-icon class="size-8 fill-current text-zinc-400" />
                    @endif
                </div>

                <div class="flex flex-col gap-2">
                    <label class="cursor-pointer">
                        <input type="file" wire:model="logoFile" accept="image/*" class="sr-only" />
                        <flux:button tag="span" size="sm" variant="outline" icon="arrow-up-tray">
                            {{ $currentLogoPath ? __('Replace logo') : __('Upload logo') }}
                        </flux:button>
                    </label>

                    @if ($currentLogoPath)
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="trash"
                            wire:click="removeLogo"
                            wire:confirm="{{ __('Remove the company logo?') }}"
                            class="text-red-500 hover:text-red-600"
                        >
                            {{ __('Remove') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            @error('logoFile')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <flux:separator class="my-2" />

        <flux:field>
            <flux:label>{{ __('Company name') }} <span class="text-red-500">*</span></flux:label>
            <flux:input wire:model="companyName" />
            <flux:error name="companyName" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tax ID') }}</flux:label>
            <flux:input wire:model="companyTaxId" placeholder="1234567890" />
            <flux:error name="companyTaxId" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Address') }}</flux:label>
            <flux:textarea wire:model="companyAddress" rows="2" />
            <flux:error name="companyAddress" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('City') }}</flux:label>
            <flux:input wire:model="companyCity" />
            <flux:error name="companyCity" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Phone') }}</flux:label>
            <flux:input wire:model="companyPhone" type="tel" />
            <flux:error name="companyPhone" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Email') }}</flux:label>
            <flux:input wire:model="companyEmail" type="email" />
            <flux:error name="companyEmail" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Website') }}</flux:label>
            <flux:input wire:model="companyWebsite" type="url" placeholder="https://example.com" />
            <flux:error name="companyWebsite" />
        </flux:field>

        <flux:separator class="my-2" />

        <flux:heading size="sm" class="mb-2">{{ __('Order rules') }}</flux:heading>

        <flux:field>
            <flux:label>{{ __('Minimum freight amount (TRY)') }}</flux:label>
            <flux:description>{{ __('Orders with a freight amount below this threshold require price approval before proceeding.') }}</flux:description>
            <flux:input wire:model="minimumFreightAmount" type="number" min="0" step="0.01" placeholder="0.00" />
            <flux:error name="minimumFreightAmount" />
        </flux:field>

        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
    </form>
    @endif

    @if ($tab === 'team')
        <flux:callout icon="information-circle" class="mb-4">{{ __('Roles apply platform-wide. If a user belongs to multiple companies, changing their role here affects all companies.') }}</flux:callout>
        <div class="space-y-2">
            @forelse ($this->teamUsers as $u)
                @php
                    $roleName = $u->roles->first()?->name ?? '';
                    $isSuperAdmin = $roleName === RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN;
                    $roleLabel = match($roleName) {
                        RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN               => __('Super Admin'),
                        RolesAndPermissionsSeeder::ROLE_TENANT_USER               => __('Admin'),
                        RolesAndPermissionsSeeder::ROLE_LOGISTICS_ORDER_CLERK     => __('Order Clerk'),
                        RolesAndPermissionsSeeder::ROLE_LOGISTICS_HR              => __('HR'),
                        RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER          => __('Viewer'),
                        default                                                   => __('No access'),
                    };
                    $roleColor = match($roleName) {
                        RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN           => 'yellow',
                        RolesAndPermissionsSeeder::ROLE_TENANT_USER           => 'lime',
                        RolesAndPermissionsSeeder::ROLE_LOGISTICS_ORDER_CLERK => 'cyan',
                        RolesAndPermissionsSeeder::ROLE_LOGISTICS_HR          => 'purple',
                        RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER      => 'zinc',
                        default                                               => 'red',
                    };
                @endphp
                <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                    <div class="flex min-w-0 items-center gap-2">
                        <flux:avatar :name="$u->name" :initials="$u->initials()" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $u->name }}</p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $u->email }}</p>
                        </div>
                    </div>
                    @if ($u->id === Auth::id() || $isSuperAdmin)
                        <flux:badge :color="$roleColor" size="sm">{{ $roleLabel }}</flux:badge>
                    @else
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="xs" variant="outline" icon-trailing="chevron-down">{{ $roleLabel }}</flux:button>
                            <flux:menu>
                                <flux:menu.item wire:click="assignRole({{ $u->id }}, 'admin')" icon="shield-check">{{ __('Admin') }}</flux:menu.item>
                                <flux:menu.item wire:click="assignRole({{ $u->id }}, 'order-clerk')" icon="clipboard-document-list">{{ __('Order Clerk') }}</flux:menu.item>
                                <flux:menu.item wire:click="assignRole({{ $u->id }}, 'hr')" icon="users">{{ __('HR') }}</flux:menu.item>
                                <flux:menu.item wire:click="assignRole({{ $u->id }}, 'viewer')" icon="eye">{{ __('Viewer') }}</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="assignRole({{ $u->id }}, 'none')" icon="x-mark" variant="danger">{{ __('Remove access') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            @empty
                <p class="text-sm text-zinc-400">{{ __('No users found.') }}</p>
            @endforelse
        </div>
    @endif
</x-settings.layout>
