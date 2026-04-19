<?php

use App\Models\Customer;
use App\Models\Order;
use App\Services\Finance\ReceivablesAgingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Finance reports')] class extends Component
{
    public string $asOfDate = '';

    public string $topCustomersPeriod = 'month_3';

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
        if ($this->asOfDate === '') {
            $this->asOfDate = now()->toDateString();
        }
    }

    private function topCustomersPeriodStart(): \Carbon\CarbonInterface
    {
        return match ($this->topCustomersPeriod) {
            'month_1' => now()->subMonth()->startOfDay(),
            'month_6' => now()->subMonths(6)->startOfDay(),
            'ytd' => now()->startOfYear(),
            'year_1' => now()->subYear()->startOfDay(),
            default => now()->subMonths(3)->startOfDay(),
        };
    }

    /**
     * @return Collection<int, array{customer_id: int, customer_name: string, order_count: int, total_freight: float, currency_code: string}>
     */
    #[Computed]
    public function topCustomers(): Collection
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return collect();
        }

        $from = $this->topCustomersPeriodStart();

        $rows = Order::query()
            ->select(
                'customer_id',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(freight_amount) as total_freight'),
                'currency_code'
            )
            ->where('tenant_id', $user->tenant_id)
            ->where('ordered_at', '>=', $from)
            ->whereNotNull('freight_amount')
            ->groupBy('customer_id', 'currency_code')
            ->orderByDesc(DB::raw('SUM(freight_amount)'))
            ->limit(15)
            ->get();

        $customerIds = $rows->pluck('customer_id')->unique()->values()->all();
        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->pluck('legal_name', 'id');

        return $rows->map(fn ($row): array => [
            'customer_id' => (int) $row->customer_id,
            'customer_name' => $customers[(int) $row->customer_id] ?? __('Customer #:id', ['id' => $row->customer_id]),
            'order_count' => (int) $row->order_count,
            'total_freight' => (float) $row->total_freight,
            'currency_code' => $row->currency_code,
        ]);
    }

    /**
     * @return array{
     *     as_of: string,
     *     by_currency: array<string, array<string, array{count: int, amount: float}>>,
     *     customer_overdue: list<array{customer_id: int, customer_name: string, overdue_amount: float, currency_code: string, max_overdue_days: int}>
     * }
     */
    #[Computed]
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
            } catch (Throwable) {
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

    {{-- Top Customers by Revenue --}}
    <flux:card class="!p-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Top customers by revenue') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Top 15 customers ranked by total freight, grouped by currency.') }}
                </flux:text>
            </div>
            <flux:select wire:model.live="topCustomersPeriod" class="w-44">
                <option value="month_1">{{ __('Last 1 month') }}</option>
                <option value="month_3">{{ __('Last 3 months') }}</option>
                <option value="month_6">{{ __('Last 6 months') }}</option>
                <option value="ytd">{{ __('Year to date') }}</option>
                <option value="year_1">{{ __('Last 12 months') }}</option>
            </flux:select>
        </div>
        @if ($this->topCustomers->isEmpty())
            <flux:text class="text-sm text-zinc-500">{{ __('No orders with freight amounts in this period.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Rank') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Orders') }}</flux:table.column>
                    <flux:table.column>{{ __('Total freight') }}</flux:table.column>
                    <flux:table.column>{{ __('Currency') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->topCustomers as $i => $row)
                        <flux:table.row :key="$row['customer_id'].'-'.$row['currency_code']">
                            <flux:table.cell class="text-zinc-400">{{ $i + 1 }}</flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $row['customer_name'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['order_count'] }}</flux:table.cell>
                            <flux:table.cell class="font-semibold">
                                {{ number_format($row['total_freight'], 2, '.', ',') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ $row['currency_code'] }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
