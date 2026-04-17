<?php

use App\Authorization\LogisticsPermission;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

new #[Title('Company profile')] class extends Component
{
    use WithFileUploads;

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

    public function mount(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            abort(403);
        }

        $tid = (int) Auth::user()->tenant_id;
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

        $tid = (int) Auth::user()->tenant_id;

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
            cache()->forget('sidebar-menu-v4-'.Auth::id().'-'.$lang);
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

        $tid = (int) Auth::user()->tenant_id;

        if ($this->currentLogoPath) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }

        TenantSetting::set($tid, 'company_logo', null);
        $this->currentLogoPath = '';
        $this->logoFile = null;

        session()->flash('status', __('Logo removed.'));
    }
}; ?>

<x-settings.layout
    :heading="__('Company profile')"
    :subheading="__('Business information used across documents and reports.')"
>
    @if (session()->has('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

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
</x-settings.layout>
