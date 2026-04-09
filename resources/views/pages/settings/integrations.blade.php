<?php

use App\Authorization\LogisticsPermission;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Integrations')] class extends Component
{
    // ── TotalEnergies ─────────────────────────────────────────────────────────
    public string $te_base_url     = '';
    public string $te_api_key      = '';
    public string $te_province     = '';
    public string $te_district     = '';

    // ── Logo ERP ──────────────────────────────────────────────────────────────
    public string $logo_company_code     = '';
    public string $logo_plant_code       = '';
    public string $logo_storage_location = '';
    public string $logo_material_code    = '';

    // ── SMS ───────────────────────────────────────────────────────────────────
    public string $sms_endpoint     = '';
    public string $sms_bearer_token = '';

    // ── WhatsApp ──────────────────────────────────────────────────────────────
    public string $wa_endpoint     = '';
    public string $wa_bearer_token = '';

    // ── Slack ─────────────────────────────────────────────────────────────────
    public string $slack_webhook_url = '';

    public function mount(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            abort(403);
        }

        $tid = (int) Auth::user()->tenant_id;

        $this->te_base_url          = TenantSetting::get($tid, 'te_base_url')     ?? config('services.totalenergies.base_url', '');
        $this->te_api_key           = TenantSetting::get($tid, 'te_api_key')      ? '••••••••' : '';
        $this->te_province          = TenantSetting::get($tid, 'te_province')     ?? env('TOTALENERGIES_PROVINCE', '');
        $this->te_district          = TenantSetting::get($tid, 'te_district')     ?? env('TOTALENERGIES_DISTRICT', '');

        $this->logo_company_code     = TenantSetting::get($tid, 'logo_company_code')     ?? config('logo_export.company_code', '');
        $this->logo_plant_code       = TenantSetting::get($tid, 'logo_plant_code')       ?? config('logo_export.plant_code', '');
        $this->logo_storage_location = TenantSetting::get($tid, 'logo_storage_location') ?? config('logo_export.storage_location', '');
        $this->logo_material_code    = TenantSetting::get($tid, 'logo_material_code')    ?? config('logo_export.material_code', '');

        $this->sms_endpoint     = TenantSetting::get($tid, 'sms_endpoint')     ?? config('customer_engagement.sms.endpoint', '');
        $this->sms_bearer_token = TenantSetting::get($tid, 'sms_bearer_token') ? '••••••••' : '';

        $this->wa_endpoint     = TenantSetting::get($tid, 'wa_endpoint')     ?? config('customer_engagement.whatsapp.endpoint', '');
        $this->wa_bearer_token = TenantSetting::get($tid, 'wa_bearer_token') ? '••••••••' : '';

        $this->slack_webhook_url = TenantSetting::get($tid, 'slack_webhook_url') ? '••••••••' : '';
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        return [
            'te_base_url'          => 'nullable|url',
            'te_api_key'           => 'nullable|string|max:200',
            'te_province'          => 'nullable|string|max:100',
            'te_district'          => 'nullable|string|max:100',
            'logo_company_code'    => 'nullable|string|max:50',
            'logo_plant_code'      => 'nullable|string|max:50',
            'logo_storage_location'=> 'nullable|string|max:50',
            'logo_material_code'   => 'nullable|string|max:50',
            'sms_endpoint'         => 'nullable|url',
            'sms_bearer_token'     => 'nullable|string|max:500',
            'wa_endpoint'          => 'nullable|url',
            'wa_bearer_token'      => 'nullable|string|max:500',
            'slack_webhook_url'    => 'nullable|url',
        ];
    }

    public function saveTotalEnergies(): void
    {
        $this->validateOnly('te_base_url');
        $this->validateOnly('te_province');
        $this->validateOnly('te_district');
        $tid = (int) Auth::user()->tenant_id;
        TenantSetting::set($tid, 'te_base_url',  $this->te_base_url  ?: null);
        TenantSetting::set($tid, 'te_province',  $this->te_province  ?: null);
        TenantSetting::set($tid, 'te_district',  $this->te_district  ?: null);
        if ($this->te_api_key !== '••••••••' && $this->te_api_key !== '') {
            TenantSetting::set($tid, 'te_api_key', $this->te_api_key, true);
            $this->te_api_key = '••••••••';
        }
        $this->dispatch('saved');
    }

    public function saveLogo(): void
    {
        $this->validateOnly('logo_company_code');
        $tid = (int) Auth::user()->tenant_id;
        TenantSetting::set($tid, 'logo_company_code',     $this->logo_company_code     ?: null);
        TenantSetting::set($tid, 'logo_plant_code',       $this->logo_plant_code       ?: null);
        TenantSetting::set($tid, 'logo_storage_location', $this->logo_storage_location ?: null);
        TenantSetting::set($tid, 'logo_material_code',    $this->logo_material_code    ?: null);
        $this->dispatch('saved');
    }

    public function saveSms(): void
    {
        $this->validateOnly('sms_endpoint');
        $tid = (int) Auth::user()->tenant_id;
        TenantSetting::set($tid, 'sms_endpoint', $this->sms_endpoint ?: null);
        if ($this->sms_bearer_token !== '••••••••' && $this->sms_bearer_token !== '') {
            TenantSetting::set($tid, 'sms_bearer_token', $this->sms_bearer_token, true);
            $this->sms_bearer_token = '••••••••';
        }
        $this->dispatch('saved');
    }

    public function saveWhatsApp(): void
    {
        $this->validateOnly('wa_endpoint');
        $tid = (int) Auth::user()->tenant_id;
        TenantSetting::set($tid, 'wa_endpoint', $this->wa_endpoint ?: null);
        if ($this->wa_bearer_token !== '••••••••' && $this->wa_bearer_token !== '') {
            TenantSetting::set($tid, 'wa_bearer_token', $this->wa_bearer_token, true);
            $this->wa_bearer_token = '••••••••';
        }
        $this->dispatch('saved');
    }

    public function saveSlack(): void
    {
        $this->validateOnly('slack_webhook_url');
        $tid = (int) Auth::user()->tenant_id;
        if ($this->slack_webhook_url !== '••••••••' && $this->slack_webhook_url !== '') {
            TenantSetting::set($tid, 'slack_webhook_url', $this->slack_webhook_url, true);
            $this->slack_webhook_url = '••••••••';
        } elseif ($this->slack_webhook_url === '') {
            TenantSetting::set($tid, 'slack_webhook_url', null);
        }
        $this->dispatch('saved');
    }

    private function isConfigured(int $tenantId, string $key): bool
    {
        return TenantSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->whereNotNull('value')
            ->exists();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        :heading="__('Integrations')"
        :subheading="__('Configure third-party API keys and external service connections. Secret values are stored encrypted per company.')"
    >
        {{-- Saved toast --}}
        <div
            x-data="{ show: false }"
            x-on:saved.window="show = true; setTimeout(() => show = false, 2500)"
            x-show="show"
            x-transition
            class="mb-4 rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-700 dark:border-green-700 dark:bg-green-900/20 dark:text-green-400"
        >
            {{ __('Saved successfully.') }}
        </div>

        <div class="space-y-8 max-w-xl">

            {{-- ── TotalEnergies ──────────────────────────────────────────── --}}
            <flux:card class="p-5">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="sm">TotalEnergies</flux:heading>
                    @php $teOk = \App\Models\TenantSetting::query()->where('tenant_id', auth()->user()->tenant_id)->where('key','te_api_key')->whereNotNull('value')->exists(); @endphp
                    <flux:badge color="{{ $teOk ? 'green' : 'zinc' }}" size="sm">
                        {{ $teOk ? __('Configured') : __('Not configured') }}
                    </flux:badge>
                </div>
                <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Fuel price quote API. Requires a signed contract with TotalEnergies.') }}</flux:text>
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>{{ __('Base URL') }}</flux:label>
                        <flux:input wire:model="te_base_url" placeholder="https://api.totalenergies.example/v1" />
                        <flux:error name="te_base_url" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('API Key') }} <span class="text-zinc-400 text-xs">({{ __('secret') }})</span></flux:label>
                        <flux:input wire:model="te_api_key" type="password" placeholder="{{ __('Enter to update') }}" />
                    </flux:field>
                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label>{{ __('Province') }}</flux:label>
                            <flux:input wire:model="te_province" placeholder="ISTANBUL" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('District') }}</flux:label>
                            <flux:input wire:model="te_district" placeholder="KADIKOY" />
                        </flux:field>
                    </div>
                </div>
                <flux:button wire:click="saveTotalEnergies" class="mt-4" size="sm">{{ __('Save') }}</flux:button>
            </flux:card>

            {{-- ── Logo ERP ────────────────────────────────────────────────── --}}
            <flux:card class="p-5">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="sm">Logo ERP</flux:heading>
                    @php $logoOk = \App\Models\TenantSetting::query()->where('tenant_id', auth()->user()->tenant_id)->where('key','logo_company_code')->whereNotNull('value')->exists(); @endphp
                    <flux:badge color="{{ $logoOk ? 'green' : 'zinc' }}" size="sm">
                        {{ $logoOk ? __('Configured') : __('Not configured') }}
                    </flux:badge>
                </div>
                <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Logo Connect XML export parameters. Contact your Logo reseller for company and plant codes.') }}</flux:text>
                <div class="grid grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>{{ __('Company code') }}</flux:label>
                        <flux:input wire:model="logo_company_code" placeholder="001" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Plant code') }}</flux:label>
                        <flux:input wire:model="logo_plant_code" placeholder="M001" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Storage location') }}</flux:label>
                        <flux:input wire:model="logo_storage_location" placeholder="0001" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Material code') }}</flux:label>
                        <flux:input wire:model="logo_material_code" placeholder="MAT001" />
                    </flux:field>
                </div>
                <flux:button wire:click="saveLogo" class="mt-4" size="sm">{{ __('Save') }}</flux:button>
            </flux:card>

            {{-- ── SMS ─────────────────────────────────────────────────────── --}}
            <flux:card class="p-5">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="sm">{{ __('SMS Notifications') }}</flux:heading>
                    @php $smsOk = \App\Models\TenantSetting::query()->where('tenant_id', auth()->user()->tenant_id)->where('key','sms_endpoint')->whereNotNull('value')->exists(); @endphp
                    <flux:badge color="{{ $smsOk ? 'green' : 'zinc' }}" size="sm">
                        {{ $smsOk ? __('Configured') : __('Not configured') }}
                    </flux:badge>
                </div>
                <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Customer SMS notifications via webhook (e.g. Netgsm, Iletimerkezi). Requires a service agreement.') }}</flux:text>
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>{{ __('Endpoint URL') }}</flux:label>
                        <flux:input wire:model="sms_endpoint" placeholder="https://api.sms-provider.example/send" />
                        <flux:error name="sms_endpoint" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Bearer token') }} <span class="text-zinc-400 text-xs">({{ __('secret') }})</span></flux:label>
                        <flux:input wire:model="sms_bearer_token" type="password" placeholder="{{ __('Enter to update') }}" />
                    </flux:field>
                </div>
                <flux:button wire:click="saveSms" class="mt-4" size="sm">{{ __('Save') }}</flux:button>
            </flux:card>

            {{-- ── WhatsApp ─────────────────────────────────────────────────── --}}
            <flux:card class="p-5">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="sm">WhatsApp</flux:heading>
                    @php $waOk = \App\Models\TenantSetting::query()->where('tenant_id', auth()->user()->tenant_id)->where('key','wa_endpoint')->whereNotNull('value')->exists(); @endphp
                    <flux:badge color="{{ $waOk ? 'green' : 'zinc' }}" size="sm">
                        {{ $waOk ? __('Configured') : __('Not configured') }}
                    </flux:badge>
                </div>
                <flux:text class="mb-4 text-sm text-zinc-500">{{ __('WhatsApp Business API for customer notifications. Requires WhatsApp Business API access.') }}</flux:text>
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>{{ __('Endpoint URL') }}</flux:label>
                        <flux:input wire:model="wa_endpoint" placeholder="https://graph.facebook.com/v20.0/..." />
                        <flux:error name="wa_endpoint" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Bearer token') }} <span class="text-zinc-400 text-xs">({{ __('secret') }})</span></flux:label>
                        <flux:input wire:model="wa_bearer_token" type="password" placeholder="{{ __('Enter to update') }}" />
                    </flux:field>
                </div>
                <flux:button wire:click="saveWhatsApp" class="mt-4" size="sm">{{ __('Save') }}</flux:button>
            </flux:card>

            {{-- ── Slack ────────────────────────────────────────────────────── --}}
            <flux:card class="p-5">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="sm">Slack</flux:heading>
                    @php $slackOk = \App\Models\TenantSetting::query()->where('tenant_id', auth()->user()->tenant_id)->where('key','slack_webhook_url')->whereNotNull('value')->exists(); @endphp
                    <flux:badge color="{{ $slackOk ? 'green' : 'zinc' }}" size="sm">
                        {{ $slackOk ? __('Configured') : __('Not configured') }}
                    </flux:badge>
                </div>
                <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Incoming webhook for operational alerts (freight threshold, shipment events).') }}</flux:text>
                <flux:field>
                    <flux:label>{{ __('Webhook URL') }} <span class="text-zinc-400 text-xs">({{ __('secret') }})</span></flux:label>
                    <flux:input wire:model="slack_webhook_url" type="password" placeholder="{{ __('Enter to update') }}" />
                    <flux:error name="slack_webhook_url" />
                </flux:field>
                <flux:button wire:click="saveSlack" class="mt-4" size="sm">{{ __('Save') }}</flux:button>
            </flux:card>

        </div>
    </x-pages::settings.layout>
</section>
