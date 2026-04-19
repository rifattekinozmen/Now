<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Finance\CashFlowProjectionService;
use App\Services\Logistics\AuditAiEvaluationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Finance summary')] class extends Component
{
    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public string $projectionDateFrom = '';

    public string $projectionDateTo = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
        if ($this->projectionDateFrom === '') {
            $this->projectionDateFrom = now()->toDateString();
        }
        if ($this->projectionDateTo === '') {
            $this->projectionDateTo = now()->addDays(30)->toDateString();
        }
    }

    public function clearDateFilters(): void
    {
        $this->reset('filterDateFrom', 'filterDateTo');
    }

    public function resetProjectionWindow(): void
    {
        $this->projectionDateFrom = now()->toDateString();
        $this->projectionDateTo = now()->addDays(30)->toDateString();
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
                    OrderStatus::Draft                => __('Draft'),
                    OrderStatus::PendingPriceApproval => __('Pending price approval'),
                    OrderStatus::Confirmed            => __('Confirmed'),
                    OrderStatus::InTransit            => __('In transit'),
                    OrderStatus::Delivered            => __('Delivered'),
                    OrderStatus::Cancelled            => __('Cancelled'),
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

        $from = Carbon::now()->startOfDay();
        $to = Carbon::now()->addDays(30)->endOfDay();
        if ($this->projectionDateFrom !== '') {
            try {
                $from = Carbon::parse($this->projectionDateFrom)->startOfDay();
            } catch (\Throwable) {
                //
            }
        }
        if ($this->projectionDateTo !== '') {
            try {
                $to = Carbon::parse($this->projectionDateTo)->endOfDay();
            } catch (\Throwable) {
                //
            }
        }
        if ($from->gt($to)) {
            $tmp = $from->copy();
            $from = $to->copy()->startOfDay();
            $to = $tmp->endOfDay();
        }

        $svc = app(CashFlowProjectionService::class);

        return $svc->projectForTenant(
            (int) $user->tenant_id,
            $from,
            $to
        );
    }

    /**
     * @return array{evaluated_orders: int, flagged: list<array{order_id: int, order_number: string, currency_code: string|null, reasons: list<string>}>}
     */
    #[Computed]
    public function auditFreightOutliers(): array
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return ['evaluated_orders' => 0, 'flagged' => []];
        }

        $from = null;
        $to = null;
        if ($this->filterDateFrom !== '') {
            try {
                $from = Carbon::parse($this->filterDateFrom);
            } catch (\Throwable) {
                //
            }
        }
        if ($this->filterDateTo !== '') {
            try {
                $to = Carbon::parse($this->filterDateTo);
            } catch (\Throwable) {
                //
            }
        }

        $svc = app(AuditAiEvaluationService::class);

        return $svc->summarizeFreightOutliersAgainstMedian(
            (int) $user->tenant_id,
            $from,
            $to
        );
    }

    /**
     * Monthly freight trend — last 6 months (TRY).
     *
     * @return list<array{month: string, total: float}>
     */
    #[Computed]
    public function monthlyFreightTrend(): array
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return [];
        }

        $driver = DB::getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', ordered_at)"
            : "DATE_FORMAT(ordered_at, '%Y-%m')";

        $rows = Order::query()
            ->selectRaw("{$monthExpr} as month, COALESCE(SUM(freight_amount), 0) as total")
            ->where('tenant_id', $user->tenant_id)
            ->where('currency_code', 'TRY')
            ->whereNotNull('freight_amount')
            ->where('ordered_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupByRaw($monthExpr)
            ->orderByRaw($monthExpr)
            ->get();

        return $rows->map(fn ($r): array => [
            'month' => $r->month,
            'total' => (float) $r->total,
        ])->all();
    }

    /**
     * @return array{evaluated_pairs: int, flagged: list<array{fuel_intake_id: int, vehicle_id: int, liters: float, expected_liters: float, reasons: list<string>}>}
     */
    #[Computed]
    public function auditFuelIntakeAnomalies(): array
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return ['evaluated_pairs' => 0, 'flagged' => []];
        }

        return app(AuditAiEvaluationService::class)->summarizeFuelIntakeAnomalies((int) $user->tenant_id);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
        <x-admin.page-header :heading="__('Finance summary')">
            <x-slot name="breadcrumb">
                <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ __('Finance summary') }}</span>
            </x-slot>
            <x-slot name="actions">
                <flux:button :href="route('admin.finance.reports')" variant="outline" wire:navigate>{{ __('Finance reports') }}</flux:button>
                <flux:button :href="route('admin.finance.payment-due-calendar')" variant="outline" wire:navigate>{{ __('Payment due calendar') }}</flux:button>
                <flux:tooltip :content="__('Bank statement CSV import')" position="bottom">
                    <flux:button icon="arrow-up-tray" variant="outline" :href="route('admin.finance.bank-statement-csv')" wire:navigate />
                </flux:tooltip>
                <flux:tooltip :content="__('Export orders CSV')" position="bottom">
                    <flux:button icon="arrow-down-tray" variant="outline" :href="route('admin.orders.export.finance.csv')" />
                </flux:tooltip>
                <flux:tooltip :content="__('Export Logo XML')" position="bottom">
                    <flux:button icon="code-bracket" variant="outline" :href="route('admin.orders.export.logo.xml')" />
                </flux:tooltip>
            </x-slot>
        </x-admin.page-header>

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

        <x-admin.filter-bar :label="__('Report period')">
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Filter KPIs and tables by order date (ordered_at). CSV export stays full tenant scope.') }}
            </flux:text>
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                <flux:input wire:model.live="filterDateFrom" type="date" :label="__('From date')" />
                <flux:input wire:model.live="filterDateTo" type="date" :label="__('To date')" />
                <flux:button type="button" variant="ghost" wire:click="clearDateFilters">{{ __('Clear date filters') }}</flux:button>
            </div>
        </x-admin.filter-bar>

        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Operational reference only') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Totals are not tax, legal, or accounting advice. Use your own controls for compliance.') }}
            </flux:callout.text>
        </flux:callout>

        <flux:card class="!p-4">
            <flux:heading size="lg" class="mb-2">{{ __('Operational audit (freight vs median)') }}</flux:heading>
            <flux:text class="mb-2 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Orders checked: :count (same filters as report period). Compared to median freight per currency in that set.', ['count' => $this->auditFreightOutliers['evaluated_orders']]) }}
            </flux:text>
            @if (count($this->auditFreightOutliers['flagged']) === 0)
                <flux:text class="text-sm text-zinc-500">{{ __('No freight outliers detected in this period.') }}</flux:text>
            @else
                <flux:callout variant="danger" icon="shield-exclamation" class="mb-4">
                    <flux:callout.heading>{{ __('Freight amounts flagged for review') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('These orders deviate more than 20% from the median freight in the filtered period.') }}</flux:callout.text>
                </flux:callout>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Order') }}</flux:table.column>
                        <flux:table.column>{{ __('Currency') }}</flux:table.column>
                        <flux:table.column>{{ __('Reason') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->auditFreightOutliers['flagged'] as $row)
                            <flux:table.row :key="$row['order_id']">
                                <flux:table.cell>
                                    <flux:link :href="route('admin.orders.show', $row['order_id'])" wire:navigate>
                                        {{ $row['order_number'] }}
                                    </flux:link>
                                </flux:table.cell>
                                <flux:table.cell>{{ $row['currency_code'] ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $row['reasons'][0] ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <flux:card class="!p-4">
            <flux:heading size="lg" class="mb-2">{{ __('Fuel intake audit (vs expected by km)') }}</flux:heading>
            <flux:text class="mb-2 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Compared consecutive fuel entries per vehicle (odometer delta × default :lpk L/km unless vehicle meta overrides).', ['lpk' => 0.35]) }}
            </flux:text>
            @if ($this->auditFuelIntakeAnomalies['evaluated_pairs'] === 0)
                <flux:text class="text-sm text-zinc-500">{{ __('Not enough fuel intake pairs to evaluate in this tenant.') }}</flux:text>
            @elseif (count($this->auditFuelIntakeAnomalies['flagged']) === 0)
                <flux:text class="text-sm text-zinc-500">{{ __('No fuel volume anomalies detected (pairs checked: :n).', ['n' => $this->auditFuelIntakeAnomalies['evaluated_pairs']]) }}</flux:text>
            @else
                <flux:callout variant="danger" icon="shield-exclamation" class="mb-4">
                    <flux:callout.heading>{{ __('Fuel volumes flagged for review') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Actual liters deviate more than 15% from expected for the odometer segment.') }}</flux:callout.text>
                </flux:callout>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                        <flux:table.column>{{ __('Liters') }}</flux:table.column>
                        <flux:table.column>{{ __('Expected (L)') }}</flux:table.column>
                        <flux:table.column>{{ __('Reason') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->auditFuelIntakeAnomalies['flagged'] as $row)
                            <flux:table.row :key="$row['fuel_intake_id']">
                                <flux:table.cell>
                                    <flux:link :href="route('admin.vehicles.index')" wire:navigate>
                                        #{{ $row['vehicle_id'] }}
                                    </flux:link>
                                </flux:table.cell>
                                <flux:table.cell>{{ number_format($row['liters'], 2, '.', ',') }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['expected_liters'], 2, '.', ',') }}</flux:table.cell>
                                <flux:table.cell>{{ $row['reasons'][0] ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Cash flow projection') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Based on order date plus customer payment term (days). Rows are filtered by expected collection (due) date range.') }}
            </flux:text>
            <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                <flux:input wire:model.live="projectionDateFrom" type="date" :label="__('Collection window from')" />
                <flux:input wire:model.live="projectionDateTo" type="date" :label="__('Collection window to')" />
                <flux:button type="button" variant="ghost" wire:click="resetProjectionWindow">{{ __('Reset projection window') }}</flux:button>
            </div>
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

        {{-- Monthly freight trend (TRY, last 6 months) --}}
        <flux:card class="!p-4">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Monthly freight trend (TRY)') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Last 6 months — TRY freight totals from confirmed orders.') }}
                    </flux:text>
                </div>
                <flux:button size="sm" variant="ghost" :href="route('admin.analytics.cost-centers')" wire:navigate icon="presentation-chart-line">
                    {{ __('Cost Center P&L') }}
                </flux:button>
            </div>
            @if (count($this->monthlyFreightTrend) === 0)
                <flux:text class="text-sm text-zinc-500">{{ __('No TRY freight data in the last 6 months.') }}</flux:text>
            @else
                @php $maxTotal = max(array_column($this->monthlyFreightTrend, 'total')) ?: 1; @endphp
                <div class="space-y-2">
                    @foreach ($this->monthlyFreightTrend as $row)
                        @php $pct = round(($row['total'] / $maxTotal) * 100); @endphp
                        <div class="flex items-center gap-3">
                            <span class="w-16 shrink-0 text-right text-xs text-zinc-500">{{ $row['month'] }}</span>
                            <div class="h-5 min-w-[2px] rounded bg-blue-500 dark:bg-blue-400"
                                 style="width: {{ $pct }}%"></div>
                            <span class="text-sm font-mono font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ number_format($row['total'], 0, '.', ',') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

    </div>
