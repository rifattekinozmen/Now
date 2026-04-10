<?php

use App\Models\Order;
use App\Services\Finance\ReceivablesAgingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Finance reports')] class extends Component
{
    public string $asOfDate = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
        if ($this->asOfDate === '') {
            $this->asOfDate = now()->toDateString();
        }
    }

    /**
     * @return array{
     *     as_of: string,
     *     by_currency: array<string, array<string, array{count: int, amount: float}>>,
     *     customer_overdue: list<array{customer_id: int, customer_name: string, overdue_amount: float, currency_code: string, max_overdue_days: int}>
     * }
     */
    #[Computed(persist: true, seconds: 300)]
    public function agingSummary(): array
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return [
                'as_of' => '',
                'by_currency' => [],
                'customer_overdue' => [],
            ];
        }

        $asOf = now()->startOfDay();
        if ($this->asOfDate !== '') {
            try {
                $asOf = Carbon::parse($this->asOfDate)->startOfDay();
            } catch (\Throwable) {
                //
            }
        }

        return app(ReceivablesAgingService::class)->summarizeForTenant((int) $user->tenant_id, $asOf);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Finance reports')">
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.index')" variant="outline" wire:navigate>{{ __('Finance summary') }}</flux:button>
            <flux:button :href="route('dashboard')" variant="ghost" wire:navigate>{{ __('Back to dashboard') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>{{ __('Operational reference only') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Aging buckets use order date plus customer payment term vs freight amount. This is not audited accounts receivable.') }}
        </flux:callout.text>
    </flux:callout>

    <flux:card class="!p-4">
        <flux:heading size="lg" class="mb-4">{{ __('Receivables aging') }}</flux:heading>
        <div class="mb-4 max-w-xs">
            <flux:input wire:model.live="asOfDate" type="date" :label="__('As of date')" />
        </div>

        @if (count($this->agingSummary['by_currency']) === 0)
            <flux:text class="text-sm text-zinc-500">{{ __('No qualifying orders for aging in this tenant.') }}</flux:text>
        @else
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('As of :date', ['date' => $this->agingSummary['as_of']]) }}
            </flux:text>
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Currency') }}</flux:table.column>
                        <flux:table.column>{{ __('Current & not yet due') }}</flux:table.column>
                        <flux:table.column>{{ __('1–30 days overdue') }}</flux:table.column>
                        <flux:table.column>{{ __('31–60 days overdue') }}</flux:table.column>
                        <flux:table.column>{{ __('61–90 days overdue') }}</flux:table.column>
                        <flux:table.column>{{ __('Over 90 days overdue') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->agingSummary['by_currency'] as $currency => $buckets)
                            <flux:table.row :key="$currency">
                                <flux:table.cell class="font-medium">{{ $currency }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ number_format($buckets['current']['amount'], 2, '.', ',') }}
                                    <span class="text-zinc-500">({{ $buckets['current']['count'] }})</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ number_format($buckets['days_1_30']['amount'], 2, '.', ',') }}
                                    <span class="text-zinc-500">({{ $buckets['days_1_30']['count'] }})</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ number_format($buckets['days_31_60']['amount'], 2, '.', ',') }}
                                    <span class="text-zinc-500">({{ $buckets['days_31_60']['count'] }})</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ number_format($buckets['days_61_90']['amount'], 2, '.', ',') }}
                                    <span class="text-zinc-500">({{ $buckets['days_61_90']['count'] }})</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ number_format($buckets['days_over_90']['amount'], 2, '.', ',') }}
                                    <span class="text-zinc-500">({{ $buckets['days_over_90']['count'] }})</span>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </flux:card>

    <flux:card class="!p-4">
        <flux:heading size="lg" class="mb-2">{{ __('Customer overdue summary') }}</flux:heading>
        <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Non-current buckets only, ranked by overdue freight total.') }}
        </flux:text>
        @if (count($this->agingSummary['customer_overdue']) === 0)
            <flux:text class="text-sm text-zinc-500">{{ __('No overdue exposure in this view.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Currency') }}</flux:table.column>
                    <flux:table.column>{{ __('Overdue freight total') }}</flux:table.column>
                    <flux:table.column>{{ __('Max days overdue') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->agingSummary['customer_overdue'] as $row)
                        <flux:table.row :key="$row['customer_id'].'-'.$row['currency_code']">
                            <flux:table.cell>
                                {{ $row['customer_name'] !== '' ? $row['customer_name'] : __('Customer #:id', ['id' => $row['customer_id']]) }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $row['currency_code'] }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($row['overdue_amount'], 2, '.', ',') }}</flux:table.cell>
                            <flux:table.cell>{{ $row['max_overdue_days'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
