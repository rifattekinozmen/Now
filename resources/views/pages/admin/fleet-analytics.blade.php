<?php

use App\Authorization\LogisticsPermission;
use App\Models\FuelIntake;
use App\Models\MaintenanceSchedule;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Services\Logistics\AuditAiEvaluationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Fleet analytics')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', Vehicle::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    #[Computed(persist: true, seconds: 300)]
    public function vehiclesWithStats(): \Illuminate\Database\Eloquent\Collection
    {
        $cutoff = now()->subDays(30);

        return Vehicle::query()
            ->withCount([
                'shipments as shipments_30d' => fn ($q) => $q->where('created_at', '>=', $cutoff),
            ])
            ->selectSub(
                DB::table('shipments as s')
                    ->selectRaw('COALESCE(SUM(o.net_weight_kg), 0)')
                    ->join('orders as o', 's.order_id', '=', 'o.id')
                    ->whereColumn('s.vehicle_id', 'vehicles.id')
                    ->where('s.created_at', '>=', $cutoff),
                'total_tonnage'
            )
            ->orderByDesc('shipments_30d')
            ->limit(20)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MaintenanceSchedule>
     */
    #[Computed]
    public function overdueMaintenances(): \Illuminate\Database\Eloquent\Collection
    {
        return MaintenanceSchedule::query()
            ->with('vehicle')
            ->where('status', 'scheduled')
            ->where('scheduled_date', '<', now()->toDateString())
            ->orderBy('scheduled_date')
            ->limit(10)
            ->get();
    }

    /**
     * @return array{flagged: list<array{vehicle_id:int, plate:string, anomaly:string}>}
     */
    #[Computed(persist: true, seconds: 300)]
    public function fuelAnomalySummary(): array
    {
        $svc = app(AuditAiEvaluationService::class);

        return $svc->summarizeFuelIntakeAnomalies(auth()->user()->tenant_id);
    }

    /**
     * @return array{total:int, overdue:int, due_30d:int}
     */
    #[Computed]
    public function maintenanceKpi(): array
    {
        return [
            'total'   => MaintenanceSchedule::query()->count(),
            'overdue' => MaintenanceSchedule::query()
                ->where('status', 'scheduled')
                ->where('scheduled_date', '<', now()->toDateString())
                ->count(),
            'due_30d' => MaintenanceSchedule::query()
                ->where('status', 'scheduled')
                ->whereBetween('scheduled_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Fleet analytics')"
        :description="__('Vehicle trips, tonnage, maintenance, and fuel anomaly overview (last 30 days).')"
    />

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total vehicles') }}</flux:text>
            <flux:heading size="lg">{{ $this->vehiclesWithStats->count() }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Overdue maintenance') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->maintenanceKpi['overdue'] > 0 ? 'text-red-500' : '' }}">
                {{ $this->maintenanceKpi['overdue'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Maintenance due in 30d') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->maintenanceKpi['due_30d'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->maintenanceKpi['due_30d'] }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Vehicles: trips + tonnage (30d) --}}
    <flux:card class="p-4">
        <flux:heading size="sm" class="mb-3">{{ __('Trips (30d)') }} / {{ __('Total tonnage') }}</flux:heading>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">{{ __('Plate') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Brand') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Trips (30d)') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Net tonnage (kg)') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Inspection') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->vehiclesWithStats as $v)
                        <tr>
                            <td class="py-2 pe-3 font-medium">
                                <flux:link :href="route('admin.vehicles.show', $v)" wire:navigate>{{ $v->plate }}</flux:link>
                            </td>
                            <td class="py-2 pe-3 text-zinc-500">{{ $v->brand }} {{ $v->model }}</td>
                            <td class="py-2 pe-3 text-end font-mono">{{ $v->shipments_30d }}</td>
                            <td class="py-2 pe-3 text-end font-mono">{{ number_format((float)$v->total_tonnage, 0) }}</td>
                            <td class="py-2 pe-3">
                                @if ($v->inspection_valid_until?->isPast())
                                    <flux:badge color="red" size="sm">{{ __('Expired') }}</flux:badge>
                                @elseif ($v->inspection_valid_until?->diffInDays(now()) < 30)
                                    <flux:badge color="yellow" size="sm">{{ $v->inspection_valid_until?->format('d M Y') }}</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">{{ $v->inspection_valid_until?->format('d M Y') ?? '—' }}</flux:badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-zinc-500">{{ __('No vehicles found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Overdue Maintenance --}}
    @if ($this->overdueMaintenances->isNotEmpty())
        <flux:card class="border border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/10">
            <flux:heading size="sm" class="mb-3 text-red-700 dark:text-red-400">{{ __('Overdue') }} — {{ __('Maintenance') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-red-200 text-sm dark:divide-red-800">
                    <thead>
                        <tr class="text-start text-zinc-500">
                            <th class="py-2 pe-3 font-medium">{{ __('Vehicle') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Title') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Scheduled date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100 dark:divide-red-900">
                        @foreach ($this->overdueMaintenances as $m)
                            <tr>
                                <td class="py-2 pe-3 font-medium">{{ $m->vehicle?->plate }}</td>
                                <td class="py-2 pe-3">{{ $m->title }}</td>
                                <td class="py-2 pe-3 font-semibold text-red-600">{{ $m->scheduled_date?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    {{-- Fuel anomaly summary --}}
    @php $fuelFlagged = $this->fuelAnomalySummary['flagged'] ?? []; @endphp
    @if (count($fuelFlagged) > 0)
        <flux:card class="border border-orange-300 bg-orange-50 p-4 dark:border-orange-700 dark:bg-orange-900/10">
            <flux:heading size="sm" class="mb-3">{{ __('Fuel anomaly') }}</flux:heading>
            <ul class="space-y-1 text-sm">
                @foreach ($fuelFlagged as $item)
                    <li class="text-orange-700 dark:text-orange-400">
                        {{ $item['plate'] ?? $item['vehicle_id'] }} — {{ $item['anomaly'] ?? '' }}
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif
</div>
