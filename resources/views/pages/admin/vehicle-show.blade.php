<?php

use App\Enums\ExpenseType;
use App\Enums\MaintenanceStatus;
use App\Enums\TyreStatus;
use App\Enums\VehicleFineStatus;
use App\Enums\VehicleFineType;
use App\Models\Document;
use App\Models\TripExpense;
use App\Models\Vehicle;
use App\Models\VehicleFine;
use App\Models\VehicleTyre;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Lazy, Title('Vehicle')] class extends Component
{
    public Vehicle $vehicle;

    #[Url]
    public string $tab = 'overview';

    public function mount(int $id): void
    {
        $this->vehicle = Vehicle::query()
            ->with(['tenant', 'shipments.order', 'fuelIntakes', 'maintenanceSchedules', 'vehicleTyres'])
            ->findOrFail($id);
        Gate::authorize('view', $this->vehicle);
    }

    /** @return array{total:int,totalAmount:float,thisMonth:float,topType:string} */
    #[Computed]
    public function expenseStats(): array
    {
        $expenses = TripExpense::query()
            ->where('vehicle_id', $this->vehicle->id)
            ->get();

        $topType = $expenses->groupBy('expense_type')
            ->map(fn ($g) => $g->sum('amount'))
            ->sortDesc()
            ->keys()
            ->first();

        return [
            'total'       => $expenses->count(),
            'totalAmount' => (float) $expenses->sum('amount'),
            'thisMonth'   => (float) $expenses->filter(fn ($e) => $e->expense_date?->isCurrentMonth())->sum('amount'),
            'topType'     => $topType instanceof ExpenseType ? $topType->label() : ($topType ? ExpenseType::from($topType)->label() : '—'),
        ];
    }

    /** @return array{total:int,thisMonth:float,last3Months:float} */
    #[Computed]
    public function fuelStats(): array
    {
        $intakes = $this->vehicle->fuelIntakes;

        // Calculate average efficiency from consecutive odometer readings
        $sorted = $intakes->whereNotNull('odometer_km')->sortBy('odometer_km')->values();
        $efficiencyValues = [];
        for ($i = 1; $i < $sorted->count(); $i++) {
            $km = (float) $sorted[$i]->odometer_km - (float) $sorted[$i - 1]->odometer_km;
            $liters = (float) $sorted[$i]->liters;
            if ($km > 0 && $liters > 0) {
                $efficiencyValues[] = $km / $liters;
            }
        }
        $avgEfficiency = count($efficiencyValues) > 0 ? array_sum($efficiencyValues) / count($efficiencyValues) : null;

        return [
            'total'        => $intakes->count(),
            'thisMonth'    => (float) $intakes->filter(fn ($f) => $f->intake_date?->isCurrentMonth())->sum('liters'),
            'last3Months'  => (float) $intakes->filter(fn ($f) => $f->intake_date?->greaterThanOrEqualTo(now()->subMonths(3)))->sum('liters'),
            'avgEfficiency' => $avgEfficiency,
        ];
    }

    /** @return array{total:int,active:int,worn:int,replacedThisMonth:int} */
    #[Computed]
    public function tyreStats(): array
    {
        $tyres = $this->vehicle->vehicleTyres;

        return [
            'total'              => $tyres->count(),
            'active'             => $tyres->filter(fn ($t) => $t->status === TyreStatus::Active)->count(),
            'worn'               => $tyres->filter(fn ($t) => $t->status === TyreStatus::Worn)->count(),
            'replacedThisMonth'  => $tyres->filter(fn ($t) => $t->status === TyreStatus::Removed && $t->removed_at?->isCurrentMonth())->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    #[Computed]
    public function vehicleDocuments(): \Illuminate\Database\Eloquent\Collection
    {
        return Document::query()
            ->where('documentable_type', Vehicle::class)
            ->where('documentable_id', $this->vehicle->id)
            ->orderByDesc('created_at')
            ->get();
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

    // ── Vehicle Fines ────────────────────────────────────────────
    public ?int $editingFineId = null;

    public string $fine_date = '';

    public string $fine_amount = '';

    public string $fine_currency_code = 'TRY';

    public string $fine_type = 'other';

    public string $fine_no = '';

    public string $fine_location = '';

    public string $fine_status = 'pending';

    public string $fine_notes = '';

    /** @return \Illuminate\Database\Eloquent\Collection<int, VehicleFine> */
    #[Computed]
    public function vehicleFines(): \Illuminate\Database\Eloquent\Collection
    {
        return VehicleFine::query()
            ->where('vehicle_id', $this->vehicle->id)
            ->orderByDesc('fine_date')
            ->get();
    }

    public function saveFine(): void
    {
        Gate::authorize('create', VehicleFine::class);

        $validated = $this->validate([
            'fine_date'          => ['required', 'date'],
            'fine_amount'        => ['required', 'numeric', 'min:0'],
            'fine_currency_code' => ['required', 'string', 'max:3'],
            'fine_type'          => ['required', 'string'],
            'fine_no'            => ['nullable', 'string', 'max:100'],
            'fine_location'      => ['nullable', 'string', 'max:255'],
            'fine_status'        => ['required', 'string'],
            'fine_notes'         => ['nullable', 'string'],
        ]);

        VehicleFine::create([
            'tenant_id'     => $this->vehicle->tenant_id,
            'vehicle_id'    => $this->vehicle->id,
            'fine_date'     => $validated['fine_date'],
            'amount'        => $validated['fine_amount'],
            'currency_code' => $validated['fine_currency_code'],
            'fine_type'     => $validated['fine_type'],
            'fine_no'       => $validated['fine_no'] ?? null,
            'location'      => $validated['fine_location'] ?? null,
            'status'        => $validated['fine_status'],
            'notes'         => $validated['fine_notes'] ?? null,
        ]);

        $this->resetFineForm();
        unset($this->vehicleFines);
        session()->flash('fine_success', __('Fine recorded.'));
    }

    public function startEditFine(int $id): void
    {
        $fine = VehicleFine::findOrFail($id);
        Gate::authorize('update', $fine);

        $this->editingFineId = $id;
        $this->fine_date = $fine->fine_date->format('Y-m-d');
        $this->fine_amount = (string) $fine->amount;
        $this->fine_currency_code = $fine->currency_code;
        $this->fine_type = $fine->fine_type->value;
        $this->fine_no = $fine->fine_no ?? '';
        $this->fine_location = $fine->location ?? '';
        $this->fine_status = $fine->status->value;
        $this->fine_notes = $fine->notes ?? '';
    }

    public function updateFine(): void
    {
        $fine = VehicleFine::findOrFail($this->editingFineId);
        Gate::authorize('update', $fine);

        $validated = $this->validate([
            'fine_date'          => ['required', 'date'],
            'fine_amount'        => ['required', 'numeric', 'min:0'],
            'fine_currency_code' => ['required', 'string', 'max:3'],
            'fine_type'          => ['required', 'string'],
            'fine_no'            => ['nullable', 'string', 'max:100'],
            'fine_location'      => ['nullable', 'string', 'max:255'],
            'fine_status'        => ['required', 'string'],
            'fine_notes'         => ['nullable', 'string'],
        ]);

        $fine->update([
            'fine_date'     => $validated['fine_date'],
            'amount'        => $validated['fine_amount'],
            'currency_code' => $validated['fine_currency_code'],
            'fine_type'     => $validated['fine_type'],
            'fine_no'       => $validated['fine_no'] ?? null,
            'location'      => $validated['fine_location'] ?? null,
            'status'        => $validated['fine_status'],
            'notes'         => $validated['fine_notes'] ?? null,
        ]);

        $this->resetFineForm();
        unset($this->vehicleFines);
        session()->flash('fine_success', __('Fine updated.'));
    }

    public function cancelFineEdit(): void
    {
        $this->resetFineForm();
    }

    public function deleteFine(int $id): void
    {
        $fine = VehicleFine::findOrFail($id);
        Gate::authorize('delete', $fine);
        $fine->delete();
        unset($this->vehicleFines);
    }

    private function resetFineForm(): void
    {
        $this->editingFineId = null;
        $this->fine_date = '';
        $this->fine_amount = '';
        $this->fine_currency_code = 'TRY';
        $this->fine_type = 'other';
        $this->fine_no = '';
        $this->fine_location = '';
        $this->fine_status = 'pending';
        $this->fine_notes = '';
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
        <flux:tab name="tyres" icon="circle-stack">{{ __('Tyres') }}</flux:tab>
        <flux:tab name="expenses" icon="banknotes">{{ __('Expenses') }}</flux:tab>
        <flux:tab name="documents" icon="folder-open">{{ __('Documents') }}</flux:tab>
        <flux:tab name="fines" icon="exclamation-triangle">{{ __('Traffic Fines') }}</flux:tab>
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
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Avg efficiency') }}</flux:text>
                @if ($this->fuelStats['avgEfficiency'] !== null)
                    <flux:heading size="lg">{{ number_format($this->fuelStats['avgEfficiency'], 2) }} <span class="text-sm font-normal text-zinc-500">km/L</span></flux:heading>
                @else
                    <flux:heading size="lg" class="text-zinc-400">—</flux:heading>
                @endif
            </flux:card>
        </div>
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                @php
                    $sortedIntakes = $vehicle->fuelIntakes->whereNotNull('odometer_km')->sortBy('odometer_km')->values();
                    $odometerPrev = [];
                    foreach ($sortedIntakes as $idx => $fi) {
                        $odometerPrev[$fi->id] = $idx > 0 ? (float) $sortedIntakes[$idx - 1]->odometer_km : null;
                    }
                @endphp
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Date') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Odometer') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Liters') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('km/L') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Unit price') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($vehicle->fuelIntakes->sortByDesc('intake_date') as $fi)
                            @php
                                $prev = $odometerPrev[$fi->id] ?? null;
                                $kml = null;
                                if ($fi->odometer_km && $prev !== null && (float)$fi->liters > 0) {
                                    $km = (float)$fi->odometer_km - $prev;
                                    if ($km > 0) {
                                        $kml = $km / (float)$fi->liters;
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="py-2 pe-3">{{ $fi->intake_date?->format('d M Y') }}</td>
                                <td class="py-2 pe-3 text-end font-mono text-xs">{{ $fi->odometer_km ? number_format((float)$fi->odometer_km, 0) : '—' }}</td>
                                <td class="py-2 pe-3 text-end font-mono">{{ number_format((float)$fi->liters, 2) }}</td>
                                <td class="py-2 pe-3 text-end font-mono text-xs">{{ $kml !== null ? number_format($kml, 2) : '—' }}</td>
                                <td class="py-2 pe-3 text-end font-mono text-xs">{{ number_format((float)$fi->unit_price, 3) }} ₺</td>
                                <td class="py-2 pe-3 text-end font-mono font-semibold">{{ number_format((float)$fi->liters * (float)$fi->unit_price, 2) }} ₺</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-zinc-500">{{ __('No fuel intakes yet.') }}</td>
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

    {{-- TAB: Tyres --}}
    @if ($tab === 'tyres')
        <div class="grid gap-3 sm:grid-cols-4">
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Total tyres') }}</flux:text>
                <flux:heading size="lg">{{ $this->tyreStats['total'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Active') }}</flux:text>
                <flux:heading size="lg" class="text-green-600">{{ $this->tyreStats['active'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Worn') }}</flux:text>
                <flux:heading size="lg" class="{{ $this->tyreStats['worn'] > 0 ? 'text-yellow-500' : '' }}">{{ $this->tyreStats['worn'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Replaced (this month)') }}</flux:text>
                <flux:heading size="lg">{{ $this->tyreStats['replacedThisMonth'] }}</flux:heading>
            </flux:card>
        </div>
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Position') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Brand') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Size') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Installed') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Tread depth (mm)') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($vehicle->vehicleTyres->sortBy('position') as $tyre)
                            @php $lowTread = $tyre->tread_depth_mm !== null && $tyre->tread_depth_mm < 3; @endphp
                            <tr class="{{ $lowTread ? 'bg-red-50 dark:bg-red-950/30' : '' }}">
                                <td class="py-2 pe-3 font-medium">{{ $tyre->position->label() }}</td>
                                <td class="py-2 pe-3">{{ $tyre->brand ?? '—' }}</td>
                                <td class="py-2 pe-3 font-mono text-xs">{{ $tyre->size ?? '—' }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $tyre->status->color() }}" size="sm">{{ $tyre->status->label() }}</flux:badge>
                                </td>
                                <td class="py-2 pe-3 text-zinc-500">
                                    {{ $tyre->installed_at?->format('d M Y') ?? '—' }}
                                    @if ($tyre->km_installed)
                                        <span class="block text-xs">{{ number_format($tyre->km_installed) }} km</span>
                                    @endif
                                </td>
                                <td class="py-2 pe-3 text-end font-mono {{ $lowTread ? 'font-bold text-red-600' : '' }}">
                                    {{ $tyre->tread_depth_mm !== null ? $tyre->tread_depth_mm.' mm' : '—' }}
                                    @if ($lowTread) <span class="ms-1 text-xs">⚠️</span> @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-zinc-500">{{ __('No tyres yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <flux:button variant="outline" icon="arrow-top-right-on-square" :href="route('admin.vehicle-tyres.index')" wire:navigate>
                    {{ __('Manage all tyres') }}</flux:button>
            </div>
        </flux:card>
    @endif

    {{-- TAB: Expenses --}}
    @if ($tab === 'expenses')
        <div class="grid gap-3 sm:grid-cols-4">
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Total records') }}</flux:text>
                <flux:heading size="lg">{{ $this->expenseStats['total'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Total amount (TRY)') }}</flux:text>
                <flux:heading size="lg">{{ number_format($this->expenseStats['totalAmount'], 2) }} ₺</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('This month (TRY)') }}</flux:text>
                <flux:heading size="lg">{{ number_format($this->expenseStats['thisMonth'], 2) }} ₺</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Top expense type') }}</flux:text>
                <flux:heading size="lg">{{ $this->expenseStats['topType'] }}</flux:heading>
            </flux:card>
        </div>
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-4 font-medium">{{ __('Date') }}</th>
                            <th class="py-2 pe-4 font-medium">{{ __('Type') }}</th>
                            <th class="py-2 pe-4 font-medium">{{ __('Driver') }}</th>
                            <th class="py-2 pe-4 font-medium text-end">{{ __('Amount') }}</th>
                            <th class="py-2 pe-4 font-medium">{{ __('KM') }}</th>
                            <th class="py-2 font-medium">{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse (TripExpense::query()->where('vehicle_id', $vehicle->id)->with('employee')->orderByDesc('expense_date')->orderByDesc('id')->take(50)->get() as $exp)
                            <tr>
                                <td class="py-2 pe-4 whitespace-nowrap">{{ $exp->expense_date->format('d.m.Y') }}</td>
                                <td class="py-2 pe-4">
                                    <flux:badge :color="$exp->expense_type->color()" size="sm">
                                        {{ $exp->expense_type->label() }}
                                    </flux:badge>
                                </td>
                                <td class="py-2 pe-4 text-zinc-500">{{ $exp->employee?->name ?? '—' }}</td>
                                <td class="py-2 pe-4 text-end font-mono">
                                    {{ number_format((float) $exp->amount, 2) }}
                                    <span class="text-xs text-zinc-400">{{ $exp->currency_code }}</span>
                                </td>
                                <td class="py-2 pe-4 font-mono text-xs text-zinc-500">
                                    {{ $exp->odometer_km ? number_format((float) $exp->odometer_km, 0) : '—' }}
                                </td>
                                <td class="py-2 text-xs text-zinc-500">{{ $exp->description ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-zinc-500">{{ __('No expenses recorded for this vehicle.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <flux:button variant="outline" icon="arrow-top-right-on-square" :href="route('admin.trip-expenses.index')" wire:navigate>
                    {{ __('View all expenses') }}
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

    {{-- TAB: Documents --}}
    @if ($tab === 'documents')
        <flux:card class="p-4">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                <flux:button :href="route('admin.documents.index')" size="sm" variant="ghost" wire:navigate>
                    {{ __('Manage all') }}
                </flux:button>
            </div>
            @if ($this->vehicleDocuments->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No documents for this vehicle yet.') }}</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-start text-zinc-500 dark:text-zinc-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Title') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Category') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('File type') }}</th>
                                <th class="py-2 font-medium">{{ __('Expires at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->vehicleDocuments as $doc)
                                @php $expired = $doc->expires_at && $doc->expires_at->isPast(); @endphp
                                <tr class="{{ $expired ? 'bg-red-50 dark:bg-red-950/20' : '' }}">
                                    <td class="py-2 pe-4 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $doc->title }}
                                        @if ($expired)
                                            <flux:badge color="red" size="sm" class="ms-1">{{ __('Expired') }}</flux:badge>
                                        @elseif ($doc->expires_at && $doc->expires_at->diffInDays() <= 30)
                                            <flux:badge color="yellow" size="sm" class="ms-1">{{ __('Expiring soon') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4">
                                        @if ($doc->category)
                                            <flux:badge color="{{ $doc->category->color() }}" size="sm">{{ $doc->category->label() }}</flux:badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4 font-mono text-xs text-zinc-500">{{ $doc->file_type?->value ?? '—' }}</td>
                                    <td class="py-2 {{ $expired ? 'font-semibold text-red-600' : 'text-zinc-500' }}">
                                        {{ $doc->expires_at?->format('d M Y') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @endif

    {{-- TAB: Traffic Fines --}}
    @if ($tab === 'fines')
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Traffic Fines') }}</flux:heading>

            @if (session('fine_success'))
                <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('fine_success') }}</flux:callout>
            @endif

            <form wire:submit="{{ $editingFineId ? 'updateFine' : 'saveFine' }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
                <flux:input wire:model="fine_date" type="date" :label="__('Fine Date')" required />
                <flux:input wire:model="fine_amount" type="number" step="0.01" :label="__('Amount')" required />
                <flux:input wire:model="fine_currency_code" :label="__('Currency')" />
                <flux:select wire:model="fine_type" :label="__('Type')">
                    <flux:select.option value="speeding">{{ __('Speeding') }}</flux:select.option>
                    <flux:select.option value="overload">{{ __('Overload') }}</flux:select.option>
                    <flux:select.option value="document">{{ __('Document / License') }}</flux:select.option>
                    <flux:select.option value="parking">{{ __('Parking') }}</flux:select.option>
                    <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="fine_no" :label="__('Fine No')" />
                <flux:input wire:model="fine_location" :label="__('Location')" />
                <flux:select wire:model="fine_status" :label="__('Status')">
                    <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                    <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                    <flux:select.option value="appealed">{{ __('Appealed') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="fine_notes" :label="__('Notes')" class="sm:col-span-2" />
                <div class="flex gap-2 items-end">
                    <flux:button type="submit" variant="primary">
                        {{ $editingFineId ? __('Save changes') : __('Add fine') }}
                    </flux:button>
                    @if ($editingFineId)
                        <flux:button type="button" variant="ghost" wire:click="cancelFineEdit">{{ __('Cancel') }}</flux:button>
                    @endif
                </div>
            </form>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Fine No') }}</flux:table.column>
                    <flux:table.column>{{ __('Location') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->vehicleFines as $fine)
                        <flux:table.row :key="$fine->id">
                            <flux:table.cell>{{ $fine->fine_date->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $fine->fine_type->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $fine->fine_no ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $fine->location ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ number_format((float) $fine->amount, 2) }} {{ $fine->currency_code }}</flux:table.cell>
                            <flux:table.cell>
                                @php $statusColor = match($fine->status) {
                                    \App\Enums\VehicleFineStatus::Paid => 'green',
                                    \App\Enums\VehicleFineStatus::Appealed => 'yellow',
                                    default => 'red',
                                }; @endphp
                                <flux:badge :color="$statusColor" size="sm">{{ $fine->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button size="sm" wire:click="startEditFine({{ $fine->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="deleteFine({{ $fine->id }})"
                                        wire:confirm="{{ __('Delete this fine record?') }}"
                                    >{{ __('Delete') }}</flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center text-zinc-500">
                                {{ __('No fines recorded.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
