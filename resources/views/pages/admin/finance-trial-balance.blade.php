<?php

use App\Models\ChartAccount;
use App\Services\Finance\TrialBalanceService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Trial balance')] class extends Component
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
     * @return list<array{chart_account_id: int, code: string, name: string, type: string, total_debit: string, total_credit: string, net: string}>
     */
    #[Computed]
    public function accountRows(): array
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return [];
        }

        try {
            return app(TrialBalanceService::class)->periodAccountTotals(
                (int) $user->tenant_id,
                $this->dateFrom,
                $this->dateTo,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, array{type: string, total_debit: string, total_credit: string, net: string}>
     */
    #[Computed]
    public function byTypeSummary(): array
    {
        return app(TrialBalanceService::class)->summarizeByAccountType($this->accountRows);
    }

    /**
     * @return array{total_debit: string, total_credit: string}
     */
    #[Computed]
    public function grandTotals(): array
    {
        return app(TrialBalanceService::class)->grandTotals($this->accountRows);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Trial balance')">
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.index')" variant="ghost" wire:navigate>{{ __('Finance summary') }}</flux:button>
            <flux:button :href="route('admin.finance.reports')" variant="ghost" wire:navigate>{{ __('Finance reports') }}</flux:button>
            <flux:button :href="route('admin.finance.balance-sheet')" variant="ghost" wire:navigate>{{ __('Balance sheet summary') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>{{ __('Operational reference only') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Period totals from posted journal lines. Not a substitute for statutory financial statements.') }}
        </flux:callout.text>
    </flux:callout>

    <x-admin.filter-bar :label="__('Period')">
        <div class="grid gap-4 sm:grid-cols-2 sm:max-w-xl">
            <flux:input wire:model.live="dateFrom" type="date" :label="__('From date')" />
            <flux:input wire:model.live="dateTo" type="date" :label="__('To date')" />
        </div>
    </x-admin.filter-bar>

    @if (count($this->accountRows) === 0)
        <flux:card>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No journal activity in this period.') }}</flux:text>
        </flux:card>
    @else
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('By account') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column>{{ __('Account') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Debit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Credit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Net') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->accountRows as $row)
                        <flux:table.row :key="$row['chart_account_id']">
                            <flux:table.cell>{{ $row['code'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['type'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['total_debit'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['total_credit'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['net'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <div class="mt-4 flex flex-wrap justify-end gap-8 border-t border-border pt-4 text-sm">
                <span><span class="text-zinc-500">{{ __('Total debit') }}:</span> {{ $this->grandTotals['total_debit'] }}</span>
                <span><span class="text-zinc-500">{{ __('Total credit') }}:</span> {{ $this->grandTotals['total_credit'] }}</span>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Net activity by account type') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Simple grouping for review; not a formal balance sheet.') }}</flux:text>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Debit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Credit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Net') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->byTypeSummary as $summary)
                        <flux:table.row :key="$summary['type']">
                            <flux:table.cell>{{ $summary['type'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $summary['total_debit'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $summary['total_credit'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $summary['net'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
