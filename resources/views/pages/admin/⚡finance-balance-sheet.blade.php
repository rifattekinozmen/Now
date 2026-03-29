<?php

use App\Models\ChartAccount;
use App\Services\Finance\BalanceSheetService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Balance sheet summary')] class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', ChartAccount::class);
        if ($this->dateFrom === '') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
        }
        if ($this->dateTo === '') {
            $this->dateTo = now()->toDateString();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function report(): ?array
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return null;
        }

        try {
            return app(BalanceSheetService::class)->periodStructuredSummary(
                (int) $user->tenant_id,
                $this->dateFrom,
                $this->dateTo,
            );
        } catch (\Throwable) {
            return null;
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-toolbar :heading="__('Balance sheet summary')">
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.index')" variant="ghost" wire:navigate>{{ __('Finance summary') }}</flux:button>
            <flux:button :href="route('admin.finance.trial-balance')" variant="ghost" wire:navigate>{{ __('Trial balance') }}</flux:button>
        </x-slot>
    </x-admin.page-toolbar>

    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>{{ __('Operational reference only') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Period net activity by account type. Not a statutory balance sheet or financial statement.') }}
        </flux:callout.text>
    </flux:callout>

    <flux:card class="!p-4">
        <flux:heading size="lg" class="mb-4">{{ __('Period') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2 sm:max-w-xl">
            <flux:input wire:model.live="dateFrom" type="date" :label="__('From date')" />
            <flux:input wire:model.live="dateTo" type="date" :label="__('To date')" />
        </div>
    </flux:card>

    @if ($this->report === null)
        <flux:card>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Unable to load report for this period.') }}</flux:text>
        </flux:card>
    @else
        @php($r = $this->report)
        @php($bs = $r['balance_sheet'])
        @php($is = $r['income_statement'])
        @php($t = $r['totals'])

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Balance sheet (by type)') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Section') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Debit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Credit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Net') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell>{{ __('Assets') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['assets']['total_debit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['assets']['total_credit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['assets']['net'] }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell>{{ __('Liabilities') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['liabilities']['total_debit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['liabilities']['total_credit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['liabilities']['net'] }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell>{{ __('Equity') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['equity']['total_debit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['equity']['total_credit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $bs['equity']['net'] }}</flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
            <div class="mt-4 flex flex-wrap justify-end gap-6 border-t border-border pt-4 text-sm">
                <span><span class="text-zinc-500">{{ __('Assets net') }}:</span> {{ $t['assets_net'] }}</span>
                <span><span class="text-zinc-500">{{ __('Liabilities + equity (net)') }}:</span> {{ $t['liabilities_plus_equity_net'] }}</span>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Income statement (period)') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Section') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Debit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Credit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Net') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell>{{ __('Revenue') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $is['revenue']['total_debit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $is['revenue']['total_credit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $is['revenue']['net'] }}</flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell>{{ __('Expense') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $is['expense']['total_debit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $is['expense']['total_credit'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $is['expense']['net'] }}</flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
            <div class="mt-4 flex flex-wrap justify-end gap-6 border-t border-border pt-4 text-sm">
                <span><span class="text-zinc-500">{{ __('Period result (revenue + expense net)') }}:</span> {{ $t['period_result_net'] }}</span>
            </div>
        </flux:card>
    @endif
</div>
