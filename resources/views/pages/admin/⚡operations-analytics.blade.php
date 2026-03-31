<?php

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Logistics\AuditAiEvaluationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Operations analytics')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    /**
     * @return list<array{month:string, count:int}>
     */
    #[Computed]
    public function monthlyShipmentTrend(): array
    {
        return Shipment::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'count' => (int) $r->count])
            ->all();
    }

    /**
     * @return list<array{status:string, label:string, count:int, percent:float}>
     */
    #[Computed]
    public function orderStatusDistribution(): array
    {
        $rows = Order::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $total = array_sum($rows);
        if ($total === 0) {
            return [];
        }

        $out = [];
        foreach (OrderStatus::cases() as $case) {
            $count = (int) ($rows[$case->value] ?? 0);
            if ($count === 0) {
                continue;
            }
            $out[] = [
                'status'  => $case->value,
                'label'   => $case->label(),
                'count'   => $count,
                'percent' => round(100 * $count / $total, 1),
            ];
        }

        return $out;
    }

    /**
     * Top 10 driver by shipment count (last 30d).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    #[Computed]
    public function driverPerformance(): \Illuminate\Support\Collection
    {
        return DB::table('shipments')
            ->join('employees', 'shipments.driver_employee_id', '=', 'employees.id')
            ->where('shipments.created_at', '>=', now()->subDays(30))
            ->selectRaw('employees.id, employees.first_name, employees.last_name, COUNT(shipments.id) as trips, COALESCE(SUM(shipments.net_weight_kg),0) as total_kg')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')
            ->orderByDesc('trips')
            ->limit(10)
            ->get();
    }

    /**
     * @return array{flagged:list<array{order_id:int, deviation:float, freight:float}>}
     */
    #[Computed]
    public function freightOutliers(): array
    {
        return app(AuditAiEvaluationService::class)->summarizeFreightOutliersAgainstMedian(
            now()->subDays(90)->toDateString(),
            now()->toDateString(),
        );
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Operations analytics')"
        :description="__('Order distribution, monthly shipment trend, driver performance (last 30 days).')"
    />

    {{-- Order status distribution --}}
    <flux:card class="p-4">
        <flux:heading size="sm" class="mb-3">{{ __('Order status') }}</flux:heading>
        <div class="space-y-2">
            @forelse ($this->orderStatusDistribution as $row)
                <div class="flex items-center gap-3">
                    <div class="w-28 truncate text-sm text-zinc-600 dark:text-zinc-400">{{ $row['label'] }}</div>
                    <div class="flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" style="height:8px">
                        <div class="h-full rounded-full bg-primary" style="width: {{ $row['percent'] }}%"></div>
                    </div>
                    <div class="w-16 text-end text-sm font-mono">{{ $row['count'] }} <span class="text-zinc-400">({{ $row['percent'] }}%)</span></div>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500">{{ __('No orders found.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    {{-- Monthly shipment trend --}}
    <flux:card class="p-4">
        <flux:heading size="sm" class="mb-3">{{ __('Monthly trend') }} — {{ __('Shipments') }}</flux:heading>
        <div class="flex items-end gap-2">
            @php $maxCount = max(array_column($this->monthlyShipmentTrend, 'count') ?: [1]); @endphp
            @forelse ($this->monthlyShipmentTrend as $row)
                @php $pct = $maxCount > 0 ? round(100 * $row['count'] / $maxCount) : 0; @endphp
                <div class="flex flex-1 flex-col items-center gap-1">
                    <div class="text-xs font-mono text-zinc-500">{{ $row['count'] }}</div>
                    <div class="w-full rounded-t bg-primary/70" style="height: {{ max(4, $pct * 1.2) }}px"></div>
                    <div class="text-xs text-zinc-500">{{ \Illuminate\Support\Str::substr($row['month'], 5) }}</div>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500">{{ __('No shipment data yet.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    {{-- Driver performance --}}
    <flux:card class="p-4">
        <flux:heading size="sm" class="mb-3">{{ __('Driver performance') }} — {{ __('last 30 days') }}</flux:heading>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">#</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Driver') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Trips (30d)') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Net tonnage (kg)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->driverPerformance as $i => $driver)
                        <tr>
                            <td class="py-2 pe-3 text-zinc-400">{{ $i + 1 }}</td>
                            <td class="py-2 pe-3 font-medium">{{ $driver->first_name }} {{ $driver->last_name }}</td>
                            <td class="py-2 pe-3 text-end font-mono">{{ $driver->trips }}</td>
                            <td class="py-2 pe-3 text-end font-mono">{{ number_format((float)$driver->total_kg, 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-zinc-500">{{ __('No driver shipment data for last 30 days.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Freight outliers --}}
    @php $flagged = $this->freightOutliers['flagged'] ?? []; @endphp
    @if (count($flagged) > 0)
        <flux:card class="border border-orange-300 bg-orange-50 p-4 dark:border-orange-700 dark:bg-orange-900/10">
            <flux:heading size="sm" class="mb-3">{{ __('Operational audit (freight vs median)') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-orange-200 text-sm dark:divide-orange-900">
                    <thead>
                        <tr class="text-start text-zinc-500">
                            <th class="py-2 pe-3 font-medium">{{ __('Order') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Freight') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Deviation') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-orange-100 dark:divide-orange-900">
                        @foreach ($flagged as $item)
                            <tr>
                                <td class="py-2 pe-3">
                                    @if (isset($item['order_id']))
                                        <flux:link :href="route('admin.orders.show', $item['order_id'])" wire:navigate>
                                            #{{ $item['order_id'] }}
                                        </flux:link>
                                    @endif
                                </td>
                                <td class="py-2 pe-3 text-end font-mono">{{ number_format((float)($item['freight'] ?? 0), 2) }}</td>
                                <td class="py-2 pe-3 text-end font-semibold text-orange-600">+{{ $item['deviation'] ?? 0 }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
</div>
