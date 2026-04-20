<?php

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Work Orders')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $vehicle_id       = '';
    public string $employee_id      = '';
    public string $title            = '';
    public string $description      = '';
    public string $type             = 'preventive';
    public string $status           = 'pending';
    public string $scheduled_at     = '';
    public string $completed_at     = '';
    public string $cost             = '';
    public string $service_provider = '';
    public string $notes            = '';

    // Filters
    public string $filterVehicle = '';
    public string $filterType    = '';
    public string $filterStatus  = '';
    public string $filterFrom    = '';
    public string $filterTo      = '';

    public string $sortColumn    = 'scheduled_at';
    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var int[] */
    public array $selectedIds = [];

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', WorkOrder::class);
        $this->scheduled_at = now()->format('Y-m-d');
    }

    public function updatedFilterVehicle(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterFrom(): void { $this->resetPage(); }
    public function updatedFilterTo(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'scheduled_at', 'type', 'status', 'cost', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedWorkOrders->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedWorkOrders->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('create', WorkOrder::class);
        $count             = WorkOrder::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            $this->delete($this->confirmingDeleteId);
        }
        $this->confirmingDeleteId = null;
    }

    /**
     * @return array{total:int, pending:int, completed:int, cost_this_month:float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'total'           => WorkOrder::query()->count(),
            'pending'         => WorkOrder::query()->where('status', WorkOrderStatus::Pending->value)->count(),
            'completed'       => WorkOrder::query()
                ->where('status', WorkOrderStatus::Completed->value)
                ->whereMonth('completed_at', now()->month)
                ->count(),
            'cost_this_month' => (float) WorkOrder::query()
                ->where('status', WorkOrderStatus::Completed->value)
                ->whereMonth('completed_at', now()->month)
                ->sum('cost'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::query()->orderBy('plate')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Employee>
     */
    #[Computed]
    public function employees(): \Illuminate\Database\Eloquent\Collection
    {
        return Employee::query()->orderBy('name')->get();
    }

    private function workOrderQuery(): Builder
    {
        $q = WorkOrder::query()->with(['vehicle', 'employee']);

        if ($this->filterVehicle !== '') {
            $q->where('vehicle_id', (int) $this->filterVehicle);
        }
        if ($this->filterType !== '') {
            $q->where('type', $this->filterType);
        }
        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }
        if ($this->filterFrom !== '') {
            $q->where('scheduled_at', '>=', $this->filterFrom);
        }
        if ($this->filterTo !== '') {
            $q->where('scheduled_at', '<=', $this->filterTo);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedWorkOrders(): LengthAwarePaginator
    {
        return $this->workOrderQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $wo                   = WorkOrder::query()->findOrFail($id);
        $this->editingId      = $id;
        $this->vehicle_id     = (string) ($wo->vehicle_id ?? '');
        $this->employee_id    = (string) ($wo->employee_id ?? '');
        $this->title          = $wo->title;
        $this->description    = $wo->description ?? '';
        $this->type           = $wo->type->value;
        $this->status         = $wo->status->value;
        $this->scheduled_at   = $wo->scheduled_at?->format('Y-m-d') ?? '';
        $this->completed_at   = $wo->completed_at?->format('Y-m-d') ?? '';
        $this->cost           = (string) ($wo->cost ?? '');
        $this->service_provider = $wo->service_provider ?? '';
        $this->notes          = $wo->notes ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'vehicle_id'       => ['nullable', 'integer', 'exists:vehicles,id'],
            'employee_id'      => ['nullable', 'integer', 'exists:employees,id'],
            'title'            => ['required', 'string', 'max:200'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'type'             => ['required', 'in:preventive,corrective,inspection,other'],
            'status'           => ['required', 'in:pending,in_progress,completed,cancelled'],
            'scheduled_at'     => ['nullable', 'date'],
            'completed_at'     => ['nullable', 'date'],
            'cost'             => ['nullable', 'numeric', 'min:0'],
            'service_provider' => ['nullable', 'string', 'max:180'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [
            'vehicle_id'       => filled($validated['vehicle_id']) ? (int) $validated['vehicle_id'] : null,
            'employee_id'      => filled($validated['employee_id']) ? (int) $validated['employee_id'] : null,
            'title'            => $validated['title'],
            'description'      => filled($validated['description']) ? $validated['description'] : null,
            'type'             => $validated['type'],
            'status'           => $validated['status'],
            'scheduled_at'     => filled($validated['scheduled_at']) ? $validated['scheduled_at'] : null,
            'completed_at'     => filled($validated['completed_at']) ? $validated['completed_at'] : null,
            'cost'             => filled($validated['cost']) ? (float) $validated['cost'] : null,
            'service_provider' => filled($validated['service_provider']) ? $validated['service_provider'] : null,
            'notes'            => filled($validated['notes']) ? $validated['notes'] : null,
        ];

        if ($this->editingId && $this->editingId > 0) {
            Gate::authorize('update', WorkOrder::query()->findOrFail($this->editingId));
            WorkOrder::query()->findOrFail($this->editingId)->update($payload);
        } else {
            Gate::authorize('create', WorkOrder::class);
            WorkOrder::query()->create($payload);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function markCompleted(int $id): void
    {
        $wo = WorkOrder::query()->findOrFail($id);
        Gate::authorize('update', $wo);
        $wo->update([
            'status'       => WorkOrderStatus::Completed->value,
            'completed_at' => now()->format('Y-m-d'),
        ]);
    }

    public function delete(int $id): void
    {
        $wo = WorkOrder::query()->findOrFail($id);
        Gate::authorize('delete', $wo);
        $wo->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->vehicle_id       = '';
        $this->employee_id      = '';
        $this->title            = '';
        $this->description      = '';
        $this->type             = 'preventive';
        $this->status           = 'pending';
        $this->scheduled_at     = now()->format('Y-m-d');
        $this->completed_at     = '';
        $this->cost             = '';
        $this->service_provider = '';
        $this->notes            = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Work Orders')"
        :description="__('Track vehicle and equipment maintenance work orders.')"
    >
        <x-slot name="actions">
            @can('create', \App\Models\WorkOrder::class)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New work order') }}
                </flux:button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    {{-- Flash --}}
    @if (session('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('bulk_deleted') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pending'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pending'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed (this month)') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['completed'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Cost this month') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['cost_this_month'], 2) }} ₺</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center justify-end gap-2">
            <flux:button variant="ghost" wire:click="$toggle('filtersOpen')" icon="{{ $filtersOpen ? 'chevron-up' : 'chevron-down' }}">
                {{ __('Filters') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <div class="mt-4 flex flex-wrap gap-4">
                <flux:select wire:model.live="filterVehicle" :label="__('Vehicle')" class="max-w-[220px]">
                    <option value="">{{ __('All vehicles') }}</option>
                    @foreach ($this->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[180px]">
                    <option value="">{{ __('All types') }}</option>
                    @foreach (\App\Enums\WorkOrderType::cases() as $wt)
                        <option value="{{ $wt->value }}">{{ $wt->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[180px]">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\WorkOrderStatus::cases() as $ws)
                        <option value="{{ $ws->value }}">{{ $ws->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live="filterFrom" type="date" :label="__('From')" class="max-w-[160px]" />
                <flux:input wire:model.live="filterTo" type="date" :label="__('To')" class="max-w-[160px]" />
            </div>
        @endif
    </flux:card>

    {{-- Create / Edit Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId > 0 ? __('Edit work order') : __('New work order') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="title" :label="__('Title')" required class="lg:col-span-2" />
                <flux:select wire:model="type" :label="__('Type')">
                    @foreach (\App\Enums\WorkOrderType::cases() as $wt)
                        <option value="{{ $wt->value }}">{{ $wt->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="vehicle_id" :label="__('Vehicle')">
                    <option value="">{{ __('— No vehicle —') }}</option>
                    @foreach ($this->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="employee_id" :label="__('Assigned to')">
                    <option value="">{{ __('— Unassigned —') }}</option>
                    @foreach ($this->employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="status" :label="__('Status')">
                    @foreach (\App\Enums\WorkOrderStatus::cases() as $ws)
                        <option value="{{ $ws->value }}">{{ $ws->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="scheduled_at" type="date" :label="__('Scheduled date')" />
                <flux:input wire:model="completed_at" type="date" :label="__('Completed date')" />
                <flux:input wire:model="cost" type="text" :label="__('Cost (₺)')" />
                <flux:input wire:model="service_provider" :label="__('Service provider')" class="lg:col-span-3" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" class="lg:col-span-3" />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" class="lg:col-span-3" />
                <div class="flex flex-wrap gap-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Bulk delete toolbar --}}
    @if (count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button type="button" variant="danger" wire:click="bulkDeleteSelected"
                wire:confirm="{{ __('Delete selected work orders?') }}">
                {{ __('Delete selected') }}
            </flux:button>
        </div>
    @endif

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="w-12 py-2 pe-3">
                            <input type="checkbox"
                                   wire:click.prevent="toggleSelectPage"
                                   @checked($this->isPageFullySelected())
                                   class="rounded border-zinc-300" />
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Title') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Vehicle') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('type')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Type') }}@if ($sortColumn === 'type') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('scheduled_at')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Scheduled') }}@if ($sortColumn === 'scheduled_at') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium text-end">
                            <button wire:click="sortBy('cost')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Cost') }}@if ($sortColumn === 'cost') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('status')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Status') }}@if ($sortColumn === 'status') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedWorkOrders as $wo)
                        <tr>
                            <td class="py-2 pe-3">
                                <input type="checkbox" wire:model="selectedIds" :value="$wo->id" class="rounded border-zinc-300" />
                            </td>
                            <td class="py-2 pe-3">
                                <span class="font-medium">{{ $wo->title }}</span>
                                @if ($wo->service_provider)
                                    <span class="block text-xs text-zinc-400">{{ $wo->service_provider }}</span>
                                @endif
                            </td>
                            <td class="py-2 pe-3 font-mono text-xs">{{ $wo->vehicle?->plate ?? '—' }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $wo->type->color() }}" size="sm">{{ $wo->type->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs">
                                {{ $wo->scheduled_at?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="py-2 pe-3 text-end font-mono text-xs">
                                {{ $wo->cost ? number_format((float) $wo->cost, 2).' ₺' : '—' }}
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $wo->status->color() }}" size="sm">{{ $wo->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 text-end">
                                <div class="flex justify-end gap-1">
                                    @can('update', $wo)
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $wo->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        @if ($wo->status->isPending())
                                            <flux:button size="sm" variant="primary" wire:click="markCompleted({{ $wo->id }})">
                                                {{ __('Complete') }}
                                            </flux:button>
                                        @endif
                                    @endcan
                                    @can('delete', $wo)
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $wo->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">
                                {{ __('No work orders yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedWorkOrders->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete work order?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
