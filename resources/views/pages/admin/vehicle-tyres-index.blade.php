<?php

use App\Enums\TyrePosition;
use App\Enums\TyreStatus;
use App\Models\Vehicle;
use App\Models\VehicleTyre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Vehicle Tyres')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $vehicle_id      = '';
    public string $brand           = '';
    public string $size            = '';
    public string $position        = 'front_left';
    public string $installed_at    = '';
    public string $km_installed    = '';
    public string $removed_at      = '';
    public string $km_removed      = '';
    public string $status          = 'active';
    public string $tread_depth_mm  = '';
    public string $supplier        = '';
    public string $notes           = '';

    // Filters
    public string $filterVehicle  = '';
    public string $filterStatus   = '';
    public string $filterPosition = '';

    public string $sortColumn    = 'installed_at';
    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var int[] */
    public array $selectedIds = [];

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', VehicleTyre::class);
        $this->installed_at = now()->format('Y-m-d');
    }

    public function updatedFilterVehicle(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterPosition(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'brand', 'position', 'installed_at', 'status', 'tread_depth_mm', 'created_at'];
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
        $pageIds = $this->paginatedTyres->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedTyres->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('create', VehicleTyre::class);
        $count             = VehicleTyre::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete-tyre')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            $this->delete($this->confirmingDeleteId);
        }
        $this->confirmingDeleteId = null;
    }

    /**
     * @return array{total:int, active:int, worn:int, replaced_this_month:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'total'               => VehicleTyre::query()->count(),
            'active'              => VehicleTyre::query()->where('status', TyreStatus::Active->value)->count(),
            'worn'                => VehicleTyre::query()->where('status', TyreStatus::Worn->value)->count(),
            'replaced_this_month' => VehicleTyre::query()
                ->where('status', TyreStatus::Removed->value)
                ->whereMonth('removed_at', now()->month)
                ->count(),
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

    private function tyreQuery(): Builder
    {
        $q = VehicleTyre::query()->with('vehicle');

        if ($this->filterVehicle !== '') {
            $q->where('vehicle_id', (int) $this->filterVehicle);
        }
        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }
        if ($this->filterPosition !== '') {
            $q->where('position', $this->filterPosition);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedTyres(): LengthAwarePaginator
    {
        return $this->tyreQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $tyre                  = VehicleTyre::query()->findOrFail($id);
        $this->editingId       = $id;
        $this->vehicle_id      = (string) $tyre->vehicle_id;
        $this->brand           = $tyre->brand ?? '';
        $this->size            = $tyre->size ?? '';
        $this->position        = $tyre->position->value;
        $this->installed_at    = $tyre->installed_at?->format('Y-m-d') ?? '';
        $this->km_installed    = (string) ($tyre->km_installed ?? '');
        $this->removed_at      = $tyre->removed_at?->format('Y-m-d') ?? '';
        $this->km_removed      = (string) ($tyre->km_removed ?? '');
        $this->status          = $tyre->status->value;
        $this->tread_depth_mm  = (string) ($tyre->tread_depth_mm ?? '');
        $this->supplier        = $tyre->supplier ?? '';
        $this->notes           = $tyre->notes ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'vehicle_id'     => ['required', 'integer', 'exists:vehicles,id'],
            'brand'          => ['nullable', 'string', 'max:100'],
            'size'           => ['nullable', 'string', 'max:40'],
            'position'       => ['required', 'in:' . implode(',', array_column(TyrePosition::cases(), 'value'))],
            'installed_at'   => ['nullable', 'date'],
            'km_installed'   => ['nullable', 'integer', 'min:0'],
            'removed_at'     => ['nullable', 'date'],
            'km_removed'     => ['nullable', 'integer', 'min:0'],
            'status'         => ['required', 'in:' . implode(',', array_column(TyreStatus::cases(), 'value'))],
            'tread_depth_mm' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'supplier'       => ['nullable', 'string', 'max:180'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'vehicle_id'     => (int) $validated['vehicle_id'],
            'brand'          => filled($validated['brand']) ? $validated['brand'] : null,
            'size'           => filled($validated['size']) ? $validated['size'] : null,
            'position'       => $validated['position'],
            'installed_at'   => filled($validated['installed_at']) ? $validated['installed_at'] : null,
            'km_installed'   => filled($validated['km_installed']) ? (int) $validated['km_installed'] : null,
            'removed_at'     => filled($validated['removed_at']) ? $validated['removed_at'] : null,
            'km_removed'     => filled($validated['km_removed']) ? (int) $validated['km_removed'] : null,
            'status'         => $validated['status'],
            'tread_depth_mm' => filled($validated['tread_depth_mm']) ? (float) $validated['tread_depth_mm'] : null,
            'supplier'       => filled($validated['supplier']) ? $validated['supplier'] : null,
            'notes'          => filled($validated['notes']) ? $validated['notes'] : null,
        ];

        if ($this->editingId && $this->editingId > 0) {
            Gate::authorize('update', VehicleTyre::query()->findOrFail($this->editingId));
            VehicleTyre::query()->findOrFail($this->editingId)->update($payload);
        } else {
            Gate::authorize('create', VehicleTyre::class);
            VehicleTyre::query()->create($payload);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $tyre = VehicleTyre::query()->findOrFail($id);
        Gate::authorize('delete', $tyre);
        $tyre->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->vehicle_id     = '';
        $this->brand          = '';
        $this->size           = '';
        $this->position       = 'front_left';
        $this->installed_at   = now()->format('Y-m-d');
        $this->km_installed   = '';
        $this->removed_at     = '';
        $this->km_removed     = '';
        $this->status         = 'active';
        $this->tread_depth_mm = '';
        $this->supplier       = '';
        $this->notes          = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Vehicle Tyres')"
        :description="__('Track vehicle tyre installations, wear, and replacements.')"
    >
        <x-slot name="actions">
            @can('create', \App\Models\VehicleTyre::class)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New tyre') }}
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
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active tyres') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['active'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Worn tyres') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['worn'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['worn'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Replaced (this month)') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['replaced_this_month'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:text class="font-medium">{{ __('Filters') }}</flux:text>
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
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
                <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\TyreStatus::cases() as $ts)
                        <option value="{{ $ts->value }}">{{ $ts->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterPosition" :label="__('Position')" class="max-w-[180px]">
                    <option value="">{{ __('All positions') }}</option>
                    @foreach (\App\Enums\TyrePosition::cases() as $tp)
                        <option value="{{ $tp->value }}">{{ $tp->label() }}</option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </flux:card>

    {{-- Create / Edit Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId > 0 ? __('Edit tyre') : __('New tyre') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="vehicle_id" :label="__('Vehicle')" required>
                    <option value="">{{ __('Select vehicle...') }}</option>
                    @foreach ($this->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="brand" :label="__('Brand')" />
                <flux:input wire:model="size" :label="__('Size')" placeholder="315/80R22.5" />
                <flux:select wire:model="position" :label="__('Position')">
                    @foreach (\App\Enums\TyrePosition::cases() as $tp)
                        <option value="{{ $tp->value }}">{{ $tp->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="status" :label="__('Status')">
                    @foreach (\App\Enums\TyreStatus::cases() as $ts)
                        <option value="{{ $ts->value }}">{{ $ts->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="tread_depth_mm" type="text" :label="__('Tread depth (mm)')" />
                <flux:input wire:model="installed_at" type="date" :label="__('Installed')" />
                <flux:input wire:model="km_installed" type="text" :label="__('KM installed')" />
                <flux:input wire:model="removed_at" type="date" :label="__('Removed at')" />
                <flux:input wire:model="km_removed" type="text" :label="__('KM removed')" />
                <flux:input wire:model="supplier" :label="__('Supplier')" class="lg:col-span-3" />
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
                wire:confirm="{{ __('Delete selected tyres?') }}">
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
                        <th class="py-2 pe-3 font-medium">{{ __('Vehicle') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('brand')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Brand / Size') }}@if ($sortColumn === 'brand') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('position')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Position') }}@if ($sortColumn === 'position') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('installed_at')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Installed') }}@if ($sortColumn === 'installed_at') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('tread_depth_mm')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Tread (mm)') }}@if ($sortColumn === 'tread_depth_mm') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
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
                    @forelse ($this->paginatedTyres as $tyre)
                        <tr class="{{ $tyre->status === \App\Enums\TyreStatus::Worn ? 'bg-yellow-50 dark:bg-yellow-950/20' : '' }}">
                            <td class="py-2 pe-3">
                                <input type="checkbox" wire:model="selectedIds" :value="$tyre->id" class="rounded border-zinc-300" />
                            </td>
                            <td class="py-2 pe-3 font-mono text-xs">{{ $tyre->vehicle?->plate ?? '—' }}</td>
                            <td class="py-2 pe-3">
                                <span class="font-medium">{{ $tyre->brand ?? '—' }}</span>
                                @if ($tyre->size)
                                    <span class="block text-xs text-zinc-400">{{ $tyre->size }}</span>
                                @endif
                            </td>
                            <td class="py-2 pe-3 text-xs">{{ $tyre->position->label() }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs">
                                {{ $tyre->installed_at?->format('d M Y') ?? '—' }}
                                @if ($tyre->km_installed)
                                    <span class="block text-zinc-400">{{ number_format($tyre->km_installed) }} km</span>
                                @endif
                            </td>
                            <td class="py-2 pe-3 text-center text-xs font-semibold {{ $tyre->tread_depth_mm && $tyre->tread_depth_mm < 3 ? 'text-red-500' : '' }}">
                                {{ $tyre->tread_depth_mm ? $tyre->tread_depth_mm.' mm' : '—' }}
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $tyre->status->color() }}" size="sm">{{ $tyre->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 text-end">
                                <div class="flex justify-end gap-1">
                                    @can('update', $tyre)
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $tyre->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                    @endcan
                                    @can('delete', $tyre)
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $tyre->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">
                                {{ __('No tyres yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedTyres->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-delete-tyre" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete tyre?') }}</flux:heading>
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
