<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Finance\CashFlowProjectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Finance summary')] class extends Component
{
    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    public function clearDateFilters(): void
    {
        $this->reset('filterDateFrom', 'filterDateTo');
    }

    /**
     * @return Builder<Order>
     */
    private function ordersScoped(): Builder
    {
        $q = Order::query();
        $this->applyOrderedAtRange($q);

        return $q;
    }

    private function applyOrderedAtRange(Builder $q): void
    {
        if ($this->filterDateFrom !== '') {
            try {
                $q->where('ordered_at', '>=', Carbon::parse($this->filterDateFrom)->startOfDay());
            } catch (\Throwable) {
                //
            }
        }

        if ($this->filterDateTo !== '') {
            try {
                $q->where('ordered_at', '<=', Carbon::parse($this->filterDateTo)->endOfDay());
            } catch (\Throwable) {
                //
            }
        }
    }

    /**
     * @return array{total_orders: int, try_freight_sum: string, open_pipeline: int, currency_kinds: int}
     */
    #[Computed]
    public function financeIndexKpis(): array
    {
        $draft = OrderStatus::Draft->value;
        $confirmed = OrderStatus::Confirmed->value;
        $inTransit = OrderStatus::InTransit->value;

        $q = Order::query();
        $this->applyOrderedAtRange($q);

        $row = $q->toBase()->selectRaw(
            'COUNT(*) as total_orders, '.
            'COALESCE(SUM(CASE WHEN currency_code = ? AND freight_amount IS NOT NULL THEN freight_amount ELSE 0 END), 0) as try_freight_sum, '.
            'SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as open_pipeline, '.
            'COUNT(DISTINCT CASE WHEN currency_code IS NOT NULL AND currency_code != ? THEN currency_code END) as currency_kinds',
            ['TRY', $draft, $confirmed, $inTransit, '']
        )->first();

        return [
            'total_orders' => (int) ($row->total_orders ?? 0),
            'try_freight_sum' => number_format((float) ($row->try_freight_sum ?? 0), 2, '.', ','),
            'open_pipeline' => (int) ($row->open_pipeline ?? 0),
            'currency_kinds' => (int) ($row->currency_kinds ?? 0),
        ];
    }

    /**
     * @return list<array{currency: string, total: string}>
     */
    #[Computed]
    public function freightByCurrency(): array
    {
        $rows = $this->ordersScoped()
            ->selectRaw('currency_code, COALESCE(SUM(freight_amount), 0) as total')
            ->whereNotNull('freight_amount')
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'currency' => (string) $r->currency_code,
                'total' => number_format((float) $r->total, 2, '.', ','),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    #[Computed]
    public function orderStatusBreakdown(): array
    {
        /** @var array<string, int> $by */
        $by = $this->ordersScoped()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $rows = [];
        foreach (OrderStatus::cases() as $case) {
            $rows[] = [
                'label' => match ($case) {
                    OrderStatus::Draft => __('Draft'),
                    OrderStatus::Confirmed => __('Confirmed'),
                    OrderStatus::InTransit => __('In transit'),
                    OrderStatus::Delivered => __('Delivered'),
                    OrderStatus::Cancelled => __('Cancelled'),
                },
                'count' => (int) ($by[$case->value] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{order_id: int, order_number: string, due_date: string, amount: string|null, currency_code: string|null, customer_name: string|null}>
     */
    #[Computed]
    public function cashFlowProjectionRows(): array
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return [];
        }

        $svc = app(CashFlowProjectionService::class);

        return $svc->projectForTenant(
            (int) $user->tenant_id,
            Carbon::now()->startOfDay(),
            Carbon::now()->addDays(30)->endOfDay()
        );
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl">{{ __('Finance summary') }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('admin.orders.export.finance.csv')" variant="outline">{{ __('Export orders CSV') }}</flux:button>
                <flux:button :href="route('dashboard')" variant="ghost" wire:navigate>{{ __('Back to dashboard') }}</flux:button>
            </div>
        </div>

        <flux:card class="!p-4">
            <flux:heading size="lg" class="mb-2">{{ __('Report period') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Filter KPIs and tables by order date (ordered_at). CSV export stays full tenant scope.') }}
            </flux:text>
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                <flux:input wire:model.live="filterDateFrom" type="date" :label="__('From date')" />
                <flux:input wire:model.live="filterDateTo" type="date" :label="__('To date')" />
                <flux:button type="button" variant="ghost" wire:click="clearDateFilters">{{ __('Clear date filters') }}</flux:button>
            </div>
        </flux:card>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="!p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total orders') }}</flux:text>
                <flux:heading size="xl">{{ $this->financeIndexKpis['total_orders'] }}</flux:heading>
            </flux:card>
            <flux:card class="!p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('TRY freight sum') }}</flux:text>
                <flux:heading size="lg">{{ $this->financeIndexKpis['try_freight_sum'] }}</flux:heading>
            </flux:card>
            <flux:card class="!p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open pipeline') }}</flux:text>
                <flux:heading size="xl">{{ $this->financeIndexKpis['open_pipeline'] }}</flux:heading>
            </flux:card>
            <flux:card class="!p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Recorded currencies') }}</flux:text>
                <flux:heading size="xl">{{ $this->financeIndexKpis['currency_kinds'] }}</flux:heading>
            </flux:card>
        </div>

        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Operational reference only') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Totals are not tax, legal, or accounting advice. Use your own controls for compliance.') }}
            </flux:callout.text>
        </flux:callout>

        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Cash flow projection (next :days days)', ['days' => 30]) }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Based on order date plus customer payment term (days).') }}
            </flux:text>
            @if (count($this->cashFlowProjectionRows) === 0)
                <flux:text class="text-sm text-zinc-500">{{ __('No projected inflows in this window.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Order') }}</flux:table.column>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Expected collection date') }}</flux:table.column>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->cashFlowProjectionRows as $row)
                            <flux:table.row :key="$row['order_id']">
                                <flux:table.cell>{{ $row['order_number'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['customer_name'] ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $row['due_date'] }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['amount'] !== null)
                                        {{ $row['amount'] }} {{ $row['currency_code'] ?? '' }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Freight totals by currency') }}</flux:heading>
                @if (count($this->freightByCurrency) === 0)
                    <flux:text class="text-sm text-zinc-500">{{ __('No freight amounts recorded.') }}</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Currency') }}</flux:table.column>
                            <flux:table.column>{{ __('Sum of freight') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->freightByCurrency as $row)
                                <flux:table.row :key="$row['currency']">
                                    <flux:table.cell>{{ $row['currency'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['total'] }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Orders by status') }}</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Count') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->orderStatusBreakdown as $row)
                            <flux:table.row :key="$row['label']">
                                <flux:table.cell>{{ $row['label'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['count'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    </div>
