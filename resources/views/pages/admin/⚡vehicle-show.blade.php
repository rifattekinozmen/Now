<?php

use App\Enums\MaintenanceStatus;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Vehicle')] class extends Component
{
    public Vehicle $vehicle;

    #[Url]
    public string $tab = 'overview';

    public function mount(int $id): void
    {
        $this->vehicle = Vehicle::query()
            ->with(['tenant', 'shipments.order', 'fuelIntakes', 'maintenanceSchedules'])
            ->findOrFail($id);

        Gate::authorize('view', $this->vehicle);
    }

    /** @return array{total:int,thisMonth:float,last3Months:float} */
    #[Computed]
    public function fuelStats(): array
    {
        $intakes = $this->vehicle->fuelIntakes;

        return [
            'total'       => $intakes->count(),
            'thisMonth'   => (float) $intakes->filter(fn ($f) => $f->intake_date?->isCurrentMonth())->sum('liters'),
            'last3Months' => (float) $intakes->filter(fn ($f) => $f->intake_date?->greaterThanOrEqualTo(now()->subMonths(3)))->sum('liters'),
        ];
    }

    /** @return array{total:int,upcoming:int,overdue:int,done:int} */
    #[Computed]
    public function maintenanceStats(): array
    {
        $schedules = $this->vehicle->maintenanceSchedules;

        return [
            'total'    => $schedules->count(),
            'upcoming' => $schedules->filter(fn ($s) => $s->status->isScheduled() && $s->scheduled_date?->isFuture())->count(),
            'overdue'  => $schedules->filter(fn ($s) => $s->status->isScheduled() && $s->scheduled_date?->isPast())->count(),
            'done'     => $schedules->filter(fn ($s) => $s->status->isDone())->count(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">
                {{ $vehicle->plate }}
                @if ($vehicle->inspection_valid_until?->isPast())
                    <flux:badge color="red" size="sm" class="ms-2">⚠️ {{ __('Inspection expired') }}</flux:badge>
                @elseif ($vehicle->inspection_valid_until?->diffInDays() < 30)
                    <flux:badge color="yellow" size="sm" class="ms-2">{{ __('Inspection soon') }}</flux:badge>
                @endif
            </flux:heading>
            <flux:text class="text-sm text-zinc-500">
                {{ implode(' · ', array_filter([$vehicle->brand, $vehicle->model, $vehicle->manufacture_year, $vehicle->vin])) }}
            </flux:text>
        </div>
        <flux:button variant="ghost" icon="arrow-left" :href="route('admin.vehicles.index')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total shipments') }}</flux:text>
            <flux:heading size="lg">{{ $vehicle->shipments->count() }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Fuel this month (L)') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->fuelStats['thisMonth'], 0) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Maintenance due') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->maintenanceStats['overdue'] > 0 ? 'text-red-500' : '' }}">
                {{ $this->maintenanceStats['overdue'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Inspection valid until') }}</flux:text>
            <flux:heading size="lg" class="{{ $vehicle->inspection_valid_until?->isPast() ? 'text-red-500' : 'text-green-600' }}">
                {{ $vehicle->inspection_valid_until?->format('d M Y') ?? '—' }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Tabs --}}
    <flux:tabs wire:model="tab">
        <flux:tab name="overview" icon="information-circle">{{ __('Overview') }}</flux:tab>
        <flux:tab name="shipments" icon="cube">{{ __('Shipments') }}</flux:tab>
        <flux:tab name="fuel" icon="bolt">{{ __('Fuel intakes') }}</flux:tab>
        <flux:tab name="maintenance" icon="wrench-screwdriver">{{ __('Maintenance') }}</flux:tab>
        <flux:tab name="activity" icon="clock">{{ __('Activity log') }}</flux:tab>
    </flux:tabs>

    {{-- TAB: Overview --}}
    @if ($tab === 'overview')
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('Vehicle details') }}</flux:heading>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Plate') }}</flux:text>
                    <flux:text class="font-medium">{{ $vehicle->plate }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Brand') }}</flux:text>
                    <flux:text class="font-medium">{{ $vehicle->brand ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Model') }}</flux:text>
                    <flux:text class="font-medium">{{ $vehicle->model ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Year') }}</flux:text>
                    <flux:text class="font-medium">{{ $vehicle->manufacture_year ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('VIN') }}</flux:text>
                    <flux:text class="font-mono font-medium">{{ $vehicle->vin ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Inspection valid until') }}</flux:text>
                    <flux:text class="font-medium {{ $vehicle->inspection_valid_until?->isPast() ? 'text-red-500 font-bold' : '' }}">
                        {{ $vehicle->inspection_valid_until?->format('d M Y') ?? '—' }}
                    </flux:text>
                </div>
            </dl>
        </flux:card>

        {{-- Quick maintenance summary --}}
        <flux:card class="p-4">
            <flux:heading size="sm" class="mb-3">{{ __('Maintenance summary') }}</flux:heading>
            <div class="flex flex-wrap gap-4">
                <div class="text-center">
                    <flux:heading size="lg">{{ $this->maintenanceStats['total'] }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">{{ __('Total') }}</flux:text>
                </div>
                <div class="text-center">
                    <flux:heading size="lg" class="text-blue-600">{{ $this->maintenanceStats['upcoming'] }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">{{ __('Upcoming') }}</flux:text>
                </div>
                <div class="text-center">
                    <flux:heading size="lg" class="{{ $this->maintenanceStats['overdue'] > 0 ? 'text-red-500' : '' }}">{{ $this->maintenanceStats['overdue'] }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">{{ __('Overdue') }}</flux:text>
                </div>
                <div class="text-center">
                    <flux:heading size="lg" class="text-green-600">{{ $this->maintenanceStats['done'] }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">{{ __('Done') }}</flux:text>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- TAB: Shipments --}}
    @if ($tab === 'shipments')
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('ID') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Order') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Created at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($vehicle->shipments->sortByDesc('created_at') as $s)
                            <tr>
                                <td class="py-2 pe-3 font-mono">#{{ $s->id }}</td>
                                <td class="py-2 pe-3">{{ $s->order?->order_number ?? '—' }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge size="sm">{{ ucfirst($s->status ?? '—') }}</flux:badge>
                                </td>
                                <td class="py-2 pe-3 text-zinc-500">{{ $s->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-zinc-500">{{ __('No shipments yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    {{-- TAB: Fuel --}}
    @if ($tab === 'fuel')
        <div class="grid gap-3 sm:grid-cols-3">
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Total intakes') }}</flux:text>
                <flux:heading size="lg">{{ $this->fuelStats['total'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('This month (L)') }}</flux:text>
                <flux:heading size="lg">{{ number_format($this->fuelStats['thisMonth'], 0) }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Last 3 months (L)') }}</flux:text>
                <flux:heading size="lg">{{ number_format($this->fuelStats['last3Months'], 0) }}</flux:heading>
            </flux:card>
        </div>
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Date') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Liters') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Unit price') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($vehicle->fuelIntakes->sortByDesc('intake_date') as $fi)
                            <tr>
                                <td class="py-2 pe-3">{{ $fi->intake_date?->format('d M Y') }}</td>
                                <td class="py-2 pe-3 text-end font-mono">{{ number_format((float)$fi->liters, 2) }}</td>
                                <td class="py-2 pe-3 text-end font-mono text-xs">{{ number_format((float)$fi->unit_price, 3) }} ₺</td>
                                <td class="py-2 pe-3 text-end font-mono font-semibold">{{ number_format((float)$fi->liters * (float)$fi->unit_price, 2) }} ₺</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-zinc-500">{{ __('No fuel intakes yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    {{-- TAB: Maintenance --}}
    @if ($tab === 'maintenance')
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Title') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Type') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Scheduled date') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Cost') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($vehicle->maintenanceSchedules->sortByDesc('scheduled_date') as $m)
                            @php $isOverdue = $m->status->isScheduled() && $m->scheduled_date?->isPast(); @endphp
                            <tr class="{{ $isOverdue ? 'bg-red-50 dark:bg-red-950/30' : '' }}">
                                <td class="py-2 pe-3">
                                    {{ $m->title }}
                                    @if ($m->service_provider)
                                        <span class="block text-xs text-zinc-400">{{ $m->service_provider }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $m->type->color() }}" size="sm">{{ $m->type->label() }}</flux:badge>
                                </td>
                                <td class="py-2 pe-3 {{ $isOverdue ? 'font-semibold text-red-600' : '' }}">
                                    {{ $m->scheduled_date?->format('d M Y') }}
                                    @if ($isOverdue) <span class="text-xs">⚠️ {{ __('Overdue') }}</span> @endif
                                </td>
                                <td class="py-2 pe-3 text-end font-mono text-xs">
                                    {{ $m->cost ? number_format((float)$m->cost, 2).' ₺' : '—' }}
                                </td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $m->status->color() }}" size="sm">{{ $m->status->label() }}</flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-zinc-500">{{ __('No maintenance schedules yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <flux:button variant="outline" icon="plus" :href="route('admin.maintenance.index')" wire:navigate>
                    {{ __('Schedule maintenance') }}
                </flux:button>
            </div>
        </flux:card>
    @endif

    {{-- TAB: Activity Log --}}
    @if ($tab === 'activity')
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('Activity log') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Date') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Event') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('User') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($vehicle->activityLogs()->with('user')->take(20)->get() as $log)
                            <tr>
                                <td class="py-2 pe-3 whitespace-nowrap text-xs text-zinc-500">{{ $log->created_at?->format('d M Y H:i') }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge size="sm" color="{{ match($log->event) { 'created' => 'green', 'deleted' => 'red', default => 'blue' } }}">
                                        {{ $log->event }}
                                    </flux:badge>
                                </td>
                                <td class="py-2 pe-3">{{ $log->user?->name ?? __('System') }}</td>
                                <td class="py-2 pe-3 text-xs text-zinc-500">{{ $log->description ?? (isset($log->properties['changed']) ? implode(', ', $log->properties['changed']) : '—') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-zinc-500">{{ __('No activity recorded yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
</div>
