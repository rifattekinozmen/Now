<?php

use App\Models\Order;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Cost Center P&L')] class extends Component
{
    public string $period = 'month_3';

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(): array
    {
        $end = now()->endOfDay();
        $start = match ($this->period) {
            'month_1' => now()->subMonth()->startOfDay(),
            'month_6' => now()->subMonths(6)->startOfDay(),
            'ytd' => now()->startOfYear()->startOfDay(),
            'year_1' => now()->subYear()->startOfDay(),
            default => now()->subMonths(3)->startOfDay(),
        };

        return [$start, $end];
    }

    /**
     * @return Collection<int, array{vehicle_id: int, plate: string, trips: int, revenue: float, fuel_cost: float, work_order_cost: float, total_expense: float, net_pl: float, margin_pct: float|null}>
     */
    #[Computed]
    public function costCenterRows(): Collection
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return collect();
        }

        $tenantId = (int) $user->tenant_id;
        [$start, $end] = $this->periodRange();

        $revenues = DB::table('shipments')
            ->join('orders', 'orders.id', '=', 'shipments.order_id')
            ->where('shipments.tenant_id', $tenantId)
            ->whereBetween('shipments.dispatched_at', [$start, $end])
            ->whereNotNull('shipments.vehicle_id')
            ->whereNotNull('orders.freight_amount')
            ->selectRaw('shipments.vehicle_id, SUM(orders.freight_amount) as revenue, COUNT(shipments.id) as trips')
            ->groupBy('shipments.vehicle_id')
            ->get()
            ->keyBy('vehicle_id');

        $fuelLiters = DB::table('fuel_intakes')
            ->where('tenant_id', $tenantId)
            ->whereBetween('recorded_at', [$start, $end])
            ->selectRaw('vehicle_id, SUM(liters) as total_liters')
            ->groupBy('vehicle_id')
            ->pluck('total_liters', 'vehicle_id');

        $avgFuelPrice = (float) (DB::table('fuel_prices')
            ->where('tenant_id', $tenantId)
            ->whereBetween('recorded_at', [$start->toDateString(), $end->toDateString()])
            ->avg('price') ?? 0.0);

        $workOrderCosts = DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('vehicle_id')
            ->whereNotNull('cost')
            ->whereBetween('scheduled_at', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('vehicle_id, SUM(cost) as total_cost')
            ->groupBy('vehicle_id')
            ->pluck('total_cost', 'vehicle_id');

        $vehicleIds = collect($revenues->keys())
            ->merge($fuelLiters->keys())
            ->merge($workOrderCosts->keys())
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($vehicleIds->isEmpty()) {
            return collect();
        }

        $vehiclePlates = Vehicle::query()
            ->whereIn('id', $vehicleIds->toArray())
            ->pluck('plate', 'id');

        return $vehicleIds->map(function (int $vehicleId) use ($revenues, $fuelLiters, $avgFuelPrice, $workOrderCosts, $vehiclePlates): array {
            $revenue = (float) ($revenues[$vehicleId]->revenue ?? 0);
            $trips = (int) ($revenues[$vehicleId]->trips ?? 0);
            $liters = (float) ($fuelLiters[$vehicleId] ?? 0);
            $fuelCost = round($liters * $avgFuelPrice, 2);
            $workCost = (float) ($workOrderCosts[$vehicleId] ?? 0);
            $totalExpense = $fuelCost + $workCost;
            $netPl = $revenue - $totalExpense;
            $margin = $revenue > 0 ? round(($netPl / $revenue) * 100, 1) : null;

            return [
                'vehicle_id' => $vehicleId,
                'plate' => $vehiclePlates[$vehicleId] ?? "#{$vehicleId}",
                'trips' => $trips,
                'revenue' => $revenue,
                'fuel_cost' => $fuelCost,
                'work_order_cost' => $workCost,
                'total_expense' => $totalExpense,
                'net_pl' => $netPl,
                'margin_pct' => $margin,
            ];
        })->sortByDesc('revenue')->values();
    }

    /**
     * @return array{total_revenue: float, total_fuel_cost: float, total_work_cost: float, total_expense: float, net_pl: float}
     */
    #[Computed]
    public function summary(): array
    {
        $rows = $this->costCenterRows;

        return [
            'total_revenue' => $rows->sum('revenue'),
            'total_fuel_cost' => $rows->sum('fuel_cost'),
            'total_work_cost' => $rows->sum('work_order_cost'),
            'total_expense' => $rows->sum('total_expense'),
            'net_pl' => $rows->sum('net_pl'),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Cost Center P&L')" :description="__('Vehicle revenue vs fuel and work order costs for the selected period.')" />

    {{-- Analytics tab navigation --}}
    <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
        <a href="{{ route('admin.analytics.fleet') }}" wire:navigate class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">{{ __('Fleet') }}</a>
        <a href="{{ route('admin.analytics.operations') }}" wire:navigate class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">{{ __('Operations') }}</a>
        <a href="{{ route('admin.analytics.cost-centers') }}" wire:navigate class="border-b-2 border-primary px-4 py-2 text-sm font-medium text-primary">{{ __('Finance P&L') }}</a>
    </div>

    {{-- Period selector --}}
    <div class="flex flex-wrap gap-2">
        @foreach ([
            'month_1' => __('Last month'),
            'month_3' => __('Last 3 months'),
            'month_6' => __('Last 6 months'),
            'ytd'     => __('Year to date'),
            'year_1'  => __('Last year'),
        ] as $value => $label)
            <flux:button
                wire:click="$set('period', '{{ $value }}')"
                variant="{{ $this->period === $value ? 'filled' : 'outline' }}"
                size="sm"
            >{{ $label }}</flux:button>
        @endforeach
    </div>

    {{-- KPI summary --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total Revenue') }}</flux:text>
            <p class="mt-1 text-xl font-bold text-emerald-600">{{ number_format($this->summary['total_revenue'], 2, '.', ',') }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Fuel Cost') }}</flux:text>
            <p class="mt-1 text-xl font-bold text-red-500">{{ number_format($this->summary['total_fuel_cost'], 2, '.', ',') }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Work Order Cost') }}</flux:text>
            <p class="mt-1 text-xl font-bold text-orange-500">{{ number_format($this->summary['total_work_cost'], 2, '.', ',') }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total Expense') }}</flux:text>
            <p class="mt-1 text-xl font-bold text-red-600">{{ number_format($this->summary['total_expense'], 2, '.', ',') }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Net P&L') }}</flux:text>
            <p class="mt-1 text-xl font-bold {{ $this->summary['net_pl'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ number_format($this->summary['net_pl'], 2, '.', ',') }}
            </p>
        </flux:card>
    </div>

    <flux:callout variant="info" icon="information-circle">
        <flux:callout.text>
            {{ __('Fuel cost is estimated using the average fuel price for the selected period multiplied by litres consumed. Work order costs use the scheduled date. Revenue is the freight total from dispatched shipments.') }}
        </flux:callout.text>
    </flux:callout>

    {{-- Detail table --}}
    <flux:card class="!p-4">
        <flux:heading size="lg" class="mb-4">{{ __('Cost Centers by Vehicle') }}</flux:heading>

        @if ($this->costCenterRows->isEmpty())
            <flux:text class="text-sm text-zinc-500">{{ __('No data found for the selected period.') }}</flux:text>
        @else
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                        <flux:table.column>{{ __('Trips') }}</flux:table.column>
                        <flux:table.column>{{ __('Revenue') }}</flux:table.column>
                        <flux:table.column>{{ __('Fuel Cost') }}</flux:table.column>
                        <flux:table.column>{{ __('Work Order Cost') }}</flux:table.column>
                        <flux:table.column>{{ __('Total Expense') }}</flux:table.column>
                        <flux:table.column>{{ __('Net P&L') }}</flux:table.column>
                        <flux:table.column>{{ __('Margin') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->costCenterRows as $row)
                            <flux:table.row :key="$row['vehicle_id']">
                                <flux:table.cell class="font-medium">{{ $row['plate'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['trips'] }}</flux:table.cell>
                                <flux:table.cell class="text-emerald-600">{{ number_format($row['revenue'], 2, '.', ',') }}</flux:table.cell>
                                <flux:table.cell class="text-red-500">{{ number_format($row['fuel_cost'], 2, '.', ',') }}</flux:table.cell>
                                <flux:table.cell class="text-orange-500">{{ number_format($row['work_order_cost'], 2, '.', ',') }}</flux:table.cell>
                                <flux:table.cell class="text-red-600">{{ number_format($row['total_expense'], 2, '.', ',') }}</flux:table.cell>
                                <flux:table.cell class="font-semibold {{ $row['net_pl'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ number_format($row['net_pl'], 2, '.', ',') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['margin_pct'] !== null)
                                        <flux:badge variant="{{ $row['margin_pct'] >= 0 ? 'success' : 'danger' }}">
                                            {{ $row['margin_pct'] }}%
                                        </flux:badge>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </flux:card>
</div>
