<?php

use App\Authorization\LogisticsPermission;
use App\Enums\DeliveryNumberStatus;
use App\Enums\LeaveStatus;
use App\Enums\PayrollStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VoucherStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Advance;
use App\Models\Leave;
use App\Models\Order;
use App\Models\Payroll;
use App\Models\Shipment;
use App\Models\Voucher;
use App\Services\Logistics\FleetSummaryService;
use App\Services\Logistics\TcmbExchangeRateService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    use RequiresLogisticsAdmin;

    public function refreshTcmb(TcmbExchangeRateService $tcmb): void
    {
        $this->ensureLogisticsAdmin();

        if ($tcmb->tryRefreshFromRemote()) {
            session()->flash('status', __('TCMB rates cached successfully.'));
        } else {
            session()->flash('error', __('Could not fetch TCMB rates. Try again later.'));
        }

        unset($this->tcmbSnapshot);
    }

    /**
     * @return array{rates: array<string, string>, at: ?string}
     */
    #[Computed]
    public function tcmbSnapshot(): array
    {
        $svc = app(TcmbExchangeRateService::class);

        return [
            'rates' => $svc->storedRates(),
            'at' => $svc->storedFetchedAt(),
        ];
    }

    /**
     * @return array{customers: int, vehicles: int, orders: int, open_shipments: int, available_pins: int}
     */
    #[Computed]
    public function dashboardKpis(): array
    {
        $tenantId = TenantContext::id() ?? 'all';

        return Cache::remember("dashboard.kpis.{$tenantId}", 60, function () use ($tenantId) {
            if ($tenantId !== 'all') {
                $row = DB::selectOne(
                    'SELECT
                        (SELECT COUNT(*) FROM customers WHERE tenant_id = ?) AS customers,
                        (SELECT COUNT(*) FROM vehicles WHERE tenant_id = ?) AS vehicles,
                        (SELECT COUNT(*) FROM orders WHERE tenant_id = ?) AS orders,
                        (SELECT COUNT(*) FROM shipments WHERE tenant_id = ? AND status NOT IN (?, ?)) AS open_shipments,
                        (SELECT COUNT(*) FROM delivery_numbers WHERE tenant_id = ? AND status = ?) AS available_pins',
                    [
                        $tenantId,
                        $tenantId,
                        $tenantId,
                        $tenantId,
                        ShipmentStatus::Delivered->value,
                        ShipmentStatus::Cancelled->value,
                        $tenantId,
                        DeliveryNumberStatus::Available->value,
                    ]
                );
            } else {
                $row = DB::selectOne(
                    'SELECT
                        (SELECT COUNT(*) FROM customers) AS customers,
                        (SELECT COUNT(*) FROM vehicles) AS vehicles,
                        (SELECT COUNT(*) FROM orders) AS orders,
                        (SELECT COUNT(*) FROM shipments WHERE status NOT IN (?, ?)) AS open_shipments,
                        (SELECT COUNT(*) FROM delivery_numbers WHERE status = ?) AS available_pins',
                    [
                        ShipmentStatus::Delivered->value,
                        ShipmentStatus::Cancelled->value,
                        DeliveryNumberStatus::Available->value,
                    ]
                );
            }

            return [
                'customers' => (int) ($row->customers ?? 0),
                'vehicles' => (int) ($row->vehicles ?? 0),
                'orders' => (int) ($row->orders ?? 0),
                'open_shipments' => (int) ($row->open_shipments ?? 0),
                'available_pins' => (int) ($row->available_pins ?? 0),
            ];
        });
    }

    /**
     * @return list<array{status: ShipmentStatus, label: string, count: int, percent: float}>
     */
    #[Computed]
    public function shipmentStatusBreakdown(): array
    {
        $tenantId = TenantContext::id() ?? 'all';

        $locale = app()->getLocale();

        return Cache::remember("dashboard.shipment-status.{$tenantId}.{$locale}", 60, function () {
            /** @var array<string, int> $byStatus */
            $byStatus = Shipment::query()
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->map(fn ($n) => (int) $n)
                ->all();

            $total = array_sum($byStatus);
            if ($total === 0) {
                return [];
            }

            $rows = [];
            foreach (ShipmentStatus::cases() as $case) {
                $count = (int) ($byStatus[$case->value] ?? 0);
                $rows[] = [
                    'status' => $case,
                    'label' => match ($case) {
                        ShipmentStatus::Planned => __('Planned'),
                        ShipmentStatus::Dispatched => __('Dispatched'),
                        ShipmentStatus::Delivered => __('Delivered'),
                        ShipmentStatus::Cancelled => __('Cancelled'),
                    },
                    'count' => $count,
                    'percent' => round(100 * $count / $total, 1),
                ];
            }

            return $rows;
        });
    }

    /**
     * @return array{total_vehicles: int, inspection_due_30d: int, active_shipments: int}
     */
    #[Computed]
    public function fleetKpi(): array
    {
        $tenantId = TenantContext::id();
        if ($tenantId === null) {
            return ['total_vehicles' => 0, 'inspection_due_30d' => 0, 'active_shipments' => 0];
        }

        return Cache::remember("dashboard.fleet-kpi.{$tenantId}", 60, fn () => app(FleetSummaryService::class)->getFleetKpi($tenantId));
    }

    /**
     * Bekleyen onay sayıları (Voucher, Leave, Payroll).
     *
     * @return array{vouchers:int, leaves:int, payrolls:int, advances:int}
     */
    #[Computed]
    public function pendingApprovals(): array
    {
        $tenantId = TenantContext::id() ?? 'all';

        return Cache::remember("dashboard.pending-approvals.{$tenantId}", 30, function () {
            return [
                'vouchers'  => Voucher::query()->where('status', VoucherStatus::Pending->value)->count(),
                'leaves'    => Leave::query()->where('status', LeaveStatus::Pending->value)->count(),
                'payrolls'  => Payroll::query()->where('status', PayrollStatus::Draft->value)->count(),
                'advances'  => Advance::query()->where('status', \App\Enums\AdvanceStatus::Pending->value)->count(),
            ];
        });
    }

    /**
     * Önümüzdeki 7 gün içinde due_date olan siparişler.
     *
     * @return array{count:int, total_freight:float}
     */
    #[Computed]
    public function upcomingDues(): array
    {
        $tenantId = TenantContext::id() ?? 'all';

        return Cache::remember("dashboard.upcoming-dues.{$tenantId}", 60, function () {
            $rows = Order::query()
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->whereIn('status', [\App\Enums\OrderStatus::Confirmed->value, \App\Enums\OrderStatus::InTransit->value])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(freight_amount), 0) as total')
                ->first();

            return [
                'count'         => (int) ($rows?->cnt ?? 0),
                'total_freight' => (float) ($rows?->total ?? 0),
            ];
        });
    }

    /**
     * Bugünün özeti — today's orders, shipments and pending approvals total.
     *
     * @return array{today_orders: int, today_shipments: int, pending_total: int, upcoming_dues: int}
     */
    #[Computed]
    public function todaySummary(): array
    {
        $tenantId = TenantContext::id() ?? 'all';
        $today = today()->toDateString();

        return Cache::remember("dashboard.today-summary.{$tenantId}.{$today}", 30, function () {
            $todayOrders = Order::query()->whereDate('created_at', today())->count();
            $todayShipments = Shipment::query()->whereDate('created_at', today())->count();
            $pa = $this->pendingApprovals;
            $pendingTotal = ($pa['vouchers'] ?? 0) + ($pa['leaves'] ?? 0) + ($pa['payrolls'] ?? 0) + ($pa['advances'] ?? 0);

            return [
                'today_orders'   => $todayOrders,
                'today_shipments' => $todayShipments,
                'pending_total'  => $pendingTotal,
                'upcoming_dues'  => $this->upcomingDues['count'],
            ];
        });
    }

    /**
     * Chart.js için sevkiyat dağılımı (yalnızca sayısı sıfırdan büyük durumlar).
     *
     * @return array{labels: list<string>, data: list<int>, colors: list<string>}|null
     */
    #[Computed]
    public function shipmentStatusChartPayload(): ?array
    {
        $rows = $this->shipmentStatusBreakdown;
        if ($rows === []) {
            return null;
        }

        $labels = [];
        $data = [];
        $palette = ['#6366f1', '#22c55e', '#eab308', '#94a3b8'];

        foreach ($rows as $row) {
            if ($row['count'] <= 0) {
                continue;
            }
            $labels[] = $row['label'];
            $data[] = $row['count'];
        }

        if ($data === []) {
            return null;
        }

        $colors = [];
        for ($i = 0, $n = count($data); $i < $n; $i++) {
            $colors[] = $palette[$i % count($palette)];
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors,
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
        <x-admin.page-header
            :heading="__('Operations overview')"
            :description="__('Tenant KPIs, FX cache, and shipment distribution.')"
        >
            <x-slot name="actions">
                @can(LogisticsPermission::ADMIN)
                    <flux:button type="button" wire:click="refreshTcmb" variant="ghost" size="sm">
                        {{ __('Refresh TCMB rates') }}
                    </flux:button>
                @endcan
            </x-slot>
        </x-admin.page-header>

        @if (session()->has('status'))
            <flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>
        @endif
        @if (session()->has('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        {{-- Bugünün Özeti --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <flux:card class="!p-3 flex items-center gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <flux:icon name="clipboard-document-list" class="size-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Today\'s orders') }}</div>
                    <div class="text-lg font-bold">{{ $this->todaySummary['today_orders'] }}</div>
                </div>
            </flux:card>
            <flux:card class="!p-3 flex items-center gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                    <flux:icon name="truck" class="size-5 text-blue-500" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Today\'s shipments') }}</div>
                    <div class="text-lg font-bold">{{ $this->todaySummary['today_shipments'] }}</div>
                </div>
            </flux:card>
            <flux:card class="!p-3 flex items-center gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $this->todaySummary['pending_total'] > 0 ? 'bg-amber-500/10' : 'bg-zinc-100 dark:bg-zinc-700' }}">
                    <flux:icon name="clock" class="size-5 {{ $this->todaySummary['pending_total'] > 0 ? 'text-amber-500' : 'text-zinc-400' }}" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Pending approvals') }}</div>
                    <div class="text-lg font-bold {{ $this->todaySummary['pending_total'] > 0 ? 'text-amber-600' : '' }}">{{ $this->todaySummary['pending_total'] }}</div>
                </div>
            </flux:card>
            <flux:card class="!p-3 flex items-center gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $this->todaySummary['upcoming_dues'] > 0 ? 'bg-red-500/10' : 'bg-zinc-100 dark:bg-zinc-700' }}">
                    <flux:icon name="exclamation-circle" class="size-5 {{ $this->todaySummary['upcoming_dues'] > 0 ? 'text-red-500' : 'text-zinc-400' }}" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Due in 7 days') }}</div>
                    <div class="text-lg font-bold {{ $this->todaySummary['upcoming_dues'] > 0 ? 'text-red-600' : '' }}">{{ $this->todaySummary['upcoming_dues'] }}</div>
                </div>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Cached FX (TCMB ForexBuying → TRY per 1 unit)') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Not financial advice. For operational reference only.') }}
                @if ($this->tcmbSnapshot['at'])
                    — {{ __('Updated:') }} {{ $this->tcmbSnapshot['at'] }}
                @endif
            </flux:text>
            <div class="flex flex-wrap gap-4 text-sm">
                @foreach (['USD', 'EUR', 'GBP'] as $ccy)
                    <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-600">
                        <span class="font-medium">{{ $ccy }}</span>
                        <span class="ms-2 text-zinc-600 dark:text-zinc-400">{{ $this->tcmbSnapshot['rates'][$ccy] ?? '—' }}</span>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Shipment status distribution') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Share of shipments by lifecycle state (tenant scope).') }}
            </flux:text>
            @if (count($this->shipmentStatusBreakdown) === 0)
                <flux:text class="text-sm text-zinc-500">{{ __('No shipments yet.') }}</flux:text>
            @else
                <div class="flex flex-col gap-4">
                    @foreach ($this->shipmentStatusBreakdown as $row)
                        @if ($row['count'] > 0)
                            <div class="min-w-0">
                                <div class="mb-1 flex justify-between gap-2 text-sm">
                                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $row['label'] }}</span>
                                    <span class="shrink-0 text-zinc-600 dark:text-zinc-400">{{ $row['count'] }} ({{ $row['percent'] }}%)</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div
                                        class="h-2 rounded-full bg-primary transition-[width] duration-300"
                                        style="width: {{ $row['percent'] }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                @if ($payload = $this->shipmentStatusChartPayload)
                    <flux:text class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Chart (same distribution as above)') }}
                    </flux:text>
                    <div
                        wire:ignore
                        class="relative mx-auto mt-2 h-52 w-full max-w-xs"
                        data-shipment-chart='@js($payload)'
                    >
                        <canvas class="max-h-full w-full"></canvas>
                    </div>
                @endif
            @endif
        </flux:card>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Customers') }}</flux:text>
                <flux:heading size="xl">{{ $this->dashboardKpis['customers'] }}</flux:heading>
            </flux:card>
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Vehicles') }}</flux:text>
                <flux:heading size="xl">{{ $this->dashboardKpis['vehicles'] }}</flux:heading>
            </flux:card>
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Orders') }}</flux:text>
                <flux:heading size="xl">{{ $this->dashboardKpis['orders'] }}</flux:heading>
            </flux:card>
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Open shipments') }}</flux:text>
                <flux:heading size="xl">{{ $this->dashboardKpis['open_shipments'] }}</flux:heading>
            </flux:card>
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('PINs available') }}</flux:text>
                <flux:heading size="xl">{{ $this->dashboardKpis['available_pins'] }}</flux:heading>
            </flux:card>
        </div>

        @canany([LogisticsPermission::ADMIN, LogisticsPermission::VIEW])
            <flux:card>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <flux:heading size="lg">{{ __('Fleet summary') }}</flux:heading>
                    <flux:button :href="route('admin.vehicles.index')" variant="ghost" size="sm" wire:navigate>{{ __('All vehicles') }}</flux:button>
                </div>
                <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Vehicles, upcoming inspections, and active shipments (tenant scope).') }}
                </flux:text>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total vehicles') }}</flux:text>
                        <flux:heading size="xl">{{ $this->fleetKpi['total_vehicles'] }}</flux:heading>
                    </div>
                    <div @class(['rounded-lg border p-3', 'border-amber-400 dark:border-amber-500' => $this->fleetKpi['inspection_due_30d'] > 0, 'border-zinc-200 dark:border-zinc-700' => $this->fleetKpi['inspection_due_30d'] === 0])>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Inspection due (30 d)') }}</flux:text>
                        <flux:heading size="xl" @class(['text-amber-600 dark:text-amber-400' => $this->fleetKpi['inspection_due_30d'] > 0])>
                            {{ $this->fleetKpi['inspection_due_30d'] }}
                        </flux:heading>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Active shipments') }}</flux:text>
                        <flux:heading size="xl">{{ $this->fleetKpi['active_shipments'] }}</flux:heading>
                    </div>
                </div>
            </flux:card>

            <div class="flex flex-wrap gap-3">
                <flux:button :href="route('admin.orders.index')" variant="primary" wire:navigate>{{ __('Orders') }}</flux:button>
                <flux:button :href="route('admin.shipments.index')" variant="filled" wire:navigate>{{ __('Shipments') }}</flux:button>
                <flux:button :href="route('admin.delivery-numbers.index')" variant="filled" wire:navigate>{{ __('PIN pool') }}</flux:button>
                <flux:button :href="route('admin.finance.index')" variant="filled" wire:navigate>{{ __('Finance summary') }}</flux:button>
                <flux:button :href="route('admin.fuel-intakes.index')" variant="outline" wire:navigate>{{ __('Fuel intakes') }}</flux:button>
            </div>

            {{-- Pending Approvals Widget --}}
            @php
                $totalPending = array_sum($this->pendingApprovals);
            @endphp
            @if ($totalPending > 0)
                <flux:card class="p-4">
                    <flux:heading size="sm" class="mb-3">{{ __('Pending approvals') }}</flux:heading>
                    <div class="grid gap-3 sm:grid-cols-4">
                        @if ($this->pendingApprovals['vouchers'] > 0)
                            <a href="{{ route('admin.finance.vouchers.index') }}" wire:navigate
                                class="flex flex-col rounded-lg border border-yellow-300 bg-yellow-50 p-3 dark:border-yellow-700 dark:bg-yellow-900/20">
                                <flux:text class="text-xs text-zinc-500">{{ __('Vouchers') }}</flux:text>
                                <flux:heading size="lg" class="text-yellow-600">{{ $this->pendingApprovals['vouchers'] }}</flux:heading>
                            </a>
                        @endif
                        @if ($this->pendingApprovals['leaves'] > 0)
                            <a href="{{ route('admin.hr.leaves.index') }}" wire:navigate
                                class="flex flex-col rounded-lg border border-yellow-300 bg-yellow-50 p-3 dark:border-yellow-700 dark:bg-yellow-900/20">
                                <flux:text class="text-xs text-zinc-500">{{ __('Leave requests') }}</flux:text>
                                <flux:heading size="lg" class="text-yellow-600">{{ $this->pendingApprovals['leaves'] }}</flux:heading>
                            </a>
                        @endif
                        @if ($this->pendingApprovals['payrolls'] > 0)
                            <a href="{{ route('admin.hr.payroll.index') }}" wire:navigate
                                class="flex flex-col rounded-lg border border-yellow-300 bg-yellow-50 p-3 dark:border-yellow-700 dark:bg-yellow-900/20">
                                <flux:text class="text-xs text-zinc-500">{{ __('Payroll') }}</flux:text>
                                <flux:heading size="lg" class="text-yellow-600">{{ $this->pendingApprovals['payrolls'] }}</flux:heading>
                            </a>
                        @endif
                        @if ($this->pendingApprovals['advances'] > 0)
                            <a href="{{ route('admin.hr.advances.index') }}" wire:navigate
                                class="flex flex-col rounded-lg border border-yellow-300 bg-yellow-50 p-3 dark:border-yellow-700 dark:bg-yellow-900/20">
                                <flux:text class="text-xs text-zinc-500">{{ __('Advances') }}</flux:text>
                                <flux:heading size="lg" class="text-yellow-600">{{ $this->pendingApprovals['advances'] }}</flux:heading>
                            </a>
                        @endif
                    </div>
                </flux:card>
            @endif

            {{-- Upcoming Dues Widget --}}
            @if ($this->upcomingDues['count'] > 0)
                <flux:card class="border border-orange-300 bg-orange-50 p-4 dark:border-orange-700 dark:bg-orange-900/10">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm">{{ __('Due in 7 days') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-600">
                                {{ $this->upcomingDues['count'] }} {{ __('orders') }}
                                · {{ number_format($this->upcomingDues['total_freight'], 2) }} {{ __('freight total') }}
                            </flux:text>
                        </div>
                        <flux:button :href="route('admin.finance.payment-due-calendar')" variant="outline" size="sm" wire:navigate>
                            {{ __('View calendar') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        @endcanany
    </div>
