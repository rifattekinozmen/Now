<?php

use App\Authorization\LogisticsPermission;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Company profile')] class extends Component
{
    public string $companyName          = '';
    public string $companyTaxId         = '';
    public string $companyAddress       = '';
    public string $companyCity          = '';
    public string $companyPhone         = '';
    public string $companyEmail         = '';
    public string $companyWebsite       = '';
    public string $minimumFreightAmount = '';

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
        ]);

        $tid = (int) Auth::user()->tenant_id;

        Tenant::where('id', $tid)->update(['name' => $this->companyName]);

        TenantSetting::set($tid, 'company_tax_id',   filled($this->companyTaxId)   ? $this->companyTaxId   : null);
        TenantSetting::set($tid, 'company_address',  filled($this->companyAddress) ? $this->companyAddress : null);
        TenantSetting::set($tid, 'company_city',     filled($this->companyCity)    ? $this->companyCity    : null);
        TenantSetting::set($tid, 'company_phone',    filled($this->companyPhone)   ? $this->companyPhone   : null);
        TenantSetting::set($tid, 'company_email',    filled($this->companyEmail)   ? $this->companyEmail   : null);
        TenantSetting::set($tid, 'company_website',        filled($this->companyWebsite)       ? $this->companyWebsite       : null);
        TenantSetting::set($tid, 'minimum_freight_amount', filled($this->minimumFreightAmount) ? $this->minimumFreightAmount : null);

        session()->flash('status', __('Company profile saved.'));
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
