<?php

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Maintenance Schedules')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $vehicle_id       = '';
    public string $title            = '';
    public string $type             = 'periodic';
    public string $scheduled_date   = '';
    public string $km_at_service    = '';
    public string $next_km          = '';
    public string $cost             = '';
    public string $service_provider = '';
    public string $notes            = '';

    // Filters
    public string $filterVehicle = '';
    public string $filterType    = '';
    public string $filterStatus  = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', MaintenanceSchedule::class);
        $this->scheduled_date = now()->format('Y-m-d');
    }

    public function updatedFilterVehicle(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    /**
     * @return array{upcoming_7d:int, overdue:int, done_this_month:int, total_cost_this_month:float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'upcoming_7d'           => MaintenanceSchedule::query()->upcoming(7)->count(),
            'overdue'               => MaintenanceSchedule::query()->overdue()->count(),
            'done_this_month'       => MaintenanceSchedule::query()
                ->where('status', MaintenanceStatus::Done->value)
                ->whereMonth('completed_date', now()->month)->count(),
            'total_cost_this_month' => (float) MaintenanceSchedule::query()
                ->where('status', MaintenanceStatus::Done->value)
                ->whereMonth('completed_date', now()->month)
                ->sum('cost'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MaintenanceSchedule>
     */
    #[Computed]
    public function upcomingList(): \Illuminate\Database\Eloquent\Collection
    {
        return MaintenanceSchedule::query()
            ->with('vehicle')
            ->upcoming(14)
            ->orderBy('scheduled_date')
            ->limit(10)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::query()->orderBy('plate')->get();
    }

    private function maintenanceQuery(): Builder
    {
        $q = MaintenanceSchedule::query()->with(['vehicle', 'assignedTo']);

        if ($this->filterVehicle !== '') {
            $q->where('vehicle_id', (int) $this->filterVehicle);
        }

        if ($this->filterType !== '') {
            $q->where('type', $this->filterType);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        return $q->orderByDesc('scheduled_date')->orderByDesc('id');
    }

    #[Computed]
    public function paginatedSchedules(): LengthAwarePaginator
    {
        return $this->maintenanceQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'vehicle_id'       => ['required', 'integer', 'exists:vehicles,id'],
            'title'            => ['required', 'string', 'max:160'],
            'type'             => ['required', 'in:periodic,inspection,repair,tire'],
            'scheduled_date'   => ['required', 'date'],
            'km_at_service'    => ['nullable', 'integer', 'min:0'],
            'next_km'          => ['nullable', 'integer', 'min:0'],
            'cost'             => ['nullable', 'numeric', 'min:0'],
            'service_provider' => ['nullable', 'string', 'max:180'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        MaintenanceSchedule::query()->create([
            'vehicle_id'       => (int) $validated['vehicle_id'],
            'title'            => $validated['title'],
            'type'             => $validated['type'],
            'status'           => MaintenanceStatus::Scheduled->value,
            'scheduled_date'   => $validated['scheduled_date'],
            'km_at_service'    => filled($validated['km_at_service']) ? (int) $validated['km_at_service'] : null,
            'next_km'          => filled($validated['next_km']) ? (int) $validated['next_km'] : null,
            'cost'             => filled($validated['cost']) ? (float) $validated['cost'] : null,
            'service_provider' => filled($validated['service_provider']) ? $validated['service_provider'] : null,
            'notes'            => filled($validated['notes']) ? $validated['notes'] : null,
        ]);

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function markDone(int $id): void
    {
        $s = MaintenanceSchedule::query()->findOrFail($id);
        $s->update([
            'status'         => MaintenanceStatus::Done->value,
            'completed_date' => now()->format('Y-m-d'),
        ]);
    }

    public function markInProgress(int $id): void
    {
        $s = MaintenanceSchedule::query()->findOrFail($id);
        $s->update(['status' => MaintenanceStatus::InProgress->value]);
    }

    public function delete(int $id): void
    {
        MaintenanceSchedule::query()->findOrFail($id)->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->vehicle_id       = '';
        $this->title            = '';
        $this->type             = 'periodic';
        $this->scheduled_date   = now()->format('Y-m-d');
        $this->km_at_service    = '';
        $this->next_km          = '';
        $this->cost             = '';
        $this->service_provider = '';
        $this->notes            = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Maintenance Schedules')"
        :description="__('Vehicle preventive maintenance calendar and service tracking.')"
    >
        <x-slot name="actions">
            <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                {{ __('Schedule maintenance') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Upcoming (14 days)') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['upcoming_7d'] > 0 ? 'text-blue-500' : '' }}">
                {{ $this->kpiStats['upcoming_7d'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Overdue') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['overdue'] > 0 ? 'text-red-500' : '' }}">
                {{ $this->kpiStats['overdue'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Done this month') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['done_this_month'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Cost this month') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['total_cost_this_month'], 2) }} ₺</flux:heading>
        </flux:card>
    </div>

    {{-- Upcoming Widget --}}
    @if ($this->upcomingList->isNotEmpty())
        <flux:card class="p-4">
            <flux:heading size="sm" class="mb-3 text-blue-600">🔔 {{ __('Coming up in 14 days') }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->upcomingList as $up)
                    <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs dark:border-blue-800 dark:bg-blue-950">
                        <flux:badge color="{{ $up->type->color() }}" size="sm">{{ $up->type->label() }}</flux:badge>
                        <span class="font-medium">{{ $up->vehicle?->plate }}</span>
                        <span class="text-zinc-500">{{ $up->title }}</span>
                        <span class="font-semibold text-blue-700 dark:text-blue-300">{{ $up->scheduled_date->format('d M') }}</span>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    {{-- Filters --}}
    <x-admin.filter-bar :label="__('Filters')">
        <flux:select wire:model.live="filterVehicle" :label="__('Vehicle')" class="max-w-[220px]">
            <option value="">{{ __('All vehicles') }}</option>
            @foreach ($this->vehicles as $v)
                <option value="{{ $v->id }}">{{ $v->plate }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[160px]">
            <option value="">{{ __('All types') }}</option>
            @foreach (\App\Enums\MaintenanceType::cases() as $mt)
                <option value="{{ $mt->value }}">{{ $mt->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\MaintenanceStatus::cases() as $ms)
                <option value="{{ $ms->value }}">{{ $ms->label() }}</option>
            @endforeach
        </flux:select>
    </x-admin.filter-bar>

    {{-- Create Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('Schedule maintenance') }}</flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="vehicle_id" :label="__('Vehicle')" required>
                    <option value="">{{ __('Select vehicle...') }}</option>
                    @foreach ($this->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="title" :label="__('Title')" required class="lg:col-span-2" />
                <flux:select wire:model="type" :label="__('Type')">
                    @foreach (\App\Enums\MaintenanceType::cases() as $mt)
                        <option value="{{ $mt->value }}">{{ $mt->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="scheduled_date" type="date" :label="__('Scheduled date')" required />
                <flux:input wire:model="service_provider" :label="__('Service provider')" />
                <flux:input wire:model="km_at_service" type="text" :label="__('KM at service')" />
                <flux:input wire:model="next_km" type="text" :label="__('Next service KM')" />
                <flux:input wire:model="cost" type="text" :label="__('Estimated cost (₺)')" />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" class="lg:col-span-3" />
                <div class="flex flex-wrap gap-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">{{ __('Vehicle') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Title') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Type') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Scheduled') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Cost') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedSchedules as $s)
                        @php
                            $isOverdue = $s->status->isScheduled() && $s->scheduled_date->isPast();
                        @endphp
                        <tr class="{{ $isOverdue ? 'bg-red-50 dark:bg-red-950/30' : '' }}">
                            <td class="py-2 pe-3 font-medium">{{ $s->vehicle?->plate }}</td>
                            <td class="py-2 pe-3">
                                {{ $s->title }}
                                @if ($s->service_provider)
                                    <span class="block text-xs text-zinc-400">{{ $s->service_provider }}</span>
                                @endif
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $s->type->color() }}" size="sm">{{ $s->type->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap {{ $isOverdue ? 'font-semibold text-red-600' : '' }}">
                                {{ $s->scheduled_date->format('d M Y') }}
                                @if ($isOverdue)
                                    <span class="block text-xs">⚠️ {{ __('Overdue') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pe-3 text-end font-mono text-xs">
                                {{ $s->cost ? number_format((float)$s->cost, 2).' ₺' : '—' }}
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $s->status->color() }}" size="sm">{{ $s->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 text-end">
                                @if ($s->status->isScheduled())
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="markInProgress({{ $s->id }})"
                                    >{{ __('Start') }}</flux:button>
                                @endif
                                @if (! $s->status->isDone())
                                    <flux:button size="sm" variant="primary"
                                        wire:click="markDone({{ $s->id }})"
                                        wire:confirm="{{ __('Mark as done?') }}"
                                    >{{ __('Done') }}</flux:button>
                                @endif
                                @if ($s->status->isScheduled())
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="delete({{ $s->id }})"
                                        wire:confirm="{{ __('Delete this schedule?') }}"
                                    >{{ __('Delete') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                {{ __('No maintenance schedules yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedSchedules->links() }}</div>
    </flux:card>
</div>
