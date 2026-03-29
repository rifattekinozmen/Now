<?php

use App\Authorization\LogisticsPermission;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Vehicle;
use App\Services\Logistics\ExcelImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Vehicles')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;
    use WithPagination;

    public $importFile = null;

    public ?int $editingVehicleId = null;

    public string $plate = '';

    public string $brand = '';

    public string $model = '';

    public ?string $inspection_valid_until = null;

    public string $filterSearch = '';

    public string $sortColumn = 'id';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Vehicle::class);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedPage(): void
    {
        $this->selectedIds = [];
    }

    /**
     * @return array{total: int, inspection_soon: int, with_brand: int, with_model: int}
     */
    #[Computed]
    public function vehicleIndexStats(): array
    {
        $until = now()->addDays(30)->toDateString();
        $today = now()->toDateString();

        $row = Vehicle::query()
            ->toBase()
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN inspection_valid_until IS NOT NULL AND inspection_valid_until <= ? AND inspection_valid_until >= ? THEN 1 ELSE 0 END) as inspection_soon, '.
                'SUM(CASE WHEN brand IS NOT NULL AND brand != ? THEN 1 ELSE 0 END) as with_brand, '.
                'SUM(CASE WHEN model IS NOT NULL AND model != ? THEN 1 ELSE 0 END) as with_model',
                [$until, $today, '', '']
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'inspection_soon' => (int) ($row->inspection_soon ?? 0),
            'with_brand' => (int) ($row->with_brand ?? 0),
            'with_model' => (int) ($row->with_model ?? 0),
        ];
    }

    /**
     * @return Builder<Vehicle>
     */
    private function vehiclesQuery(): Builder
    {
        $q = Vehicle::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('plate', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model', 'like', $term);
            });
        }

        $allowed = ['id', 'plate', 'brand', 'model', 'inspection_valid_until', 'created_at'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'id';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    #[Computed]
    public function paginatedVehicles(): LengthAwarePaginator
    {
        return $this->vehiclesQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'plate', 'brand', 'model', 'inspection_valid_until', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
        $this->selectedIds = [];
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedVehicles->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedVehicles->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $this->selectedIds);
        $allSelected = $pageIds !== [] && count(array_diff($pageIds, $selected)) === 0;

        if ($allSelected) {
            $this->selectedIds = array_values(array_diff($selected, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($selected, $pageIds)));
        }
    }

    public function bulkDeleteSelected(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);

        if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $vehicles = Vehicle::query()->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($vehicles as $vehicle) {
            Gate::authorize('delete', $vehicle);
            $vehicle->delete();
            $deleted++;
        }

        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('bulk_deleted', $deleted);
    }

    public function saveVehicle(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);

        Gate::authorize('create', Vehicle::class);

        $validated = $this->validate([
            'plate' => ['required', 'string', 'max:32'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'inspection_valid_until' => ['nullable', 'date'],
        ]);

        Vehicle::query()->create([
            'plate' => strtoupper($validated['plate']),
            'brand' => $validated['brand'] ?: null,
            'model' => $validated['model'] ?: null,
            'inspection_valid_until' => $validated['inspection_valid_until'],
        ]);

        $this->reset('plate', 'brand', 'model', 'inspection_valid_until');
    }

    public function startEdit(int $vehicleId): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);

        $vehicle = Vehicle::query()->findOrFail($vehicleId);
        Gate::authorize('update', $vehicle);

        $this->editingVehicleId = $vehicle->id;
        $this->plate = $vehicle->plate;
        $this->brand = $vehicle->brand ?? '';
        $this->model = $vehicle->model ?? '';
        $this->inspection_valid_until = $vehicle->inspection_valid_until?->format('Y-m-d');
    }

    public function cancelVehicleEdit(): void
    {
        $this->editingVehicleId = null;
        $this->reset('plate', 'brand', 'model', 'inspection_valid_until');
    }

    public function updateVehicle(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);

        if ($this->editingVehicleId === null) {
            return;
        }

        $vehicle = Vehicle::query()->findOrFail($this->editingVehicleId);
        Gate::authorize('update', $vehicle);

        $validated = $this->validate([
            'plate' => ['required', 'string', 'max:32'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'inspection_valid_until' => ['nullable', 'date'],
        ]);

        $vehicle->update([
            'plate' => strtoupper($validated['plate']),
            'brand' => $validated['brand'] ?: null,
            'model' => $validated['model'] ?: null,
            'inspection_valid_until' => $validated['inspection_valid_until'],
        ]);

        $this->cancelVehicleEdit();
    }

    public function importVehicles(ExcelImportService $excelImport): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);

        $tenantId = auth()->user()?->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $stored = $this->importFile->store('imports', 'local');
        $path = Storage::disk('local')->path($stored);
        $result = $excelImport->importVehiclesFromPath($path, (int) $tenantId);
        Storage::disk('local')->delete($stored);

        session()->flash('import_created', $result['created']);
        session()->flash('import_errors', $result['errors']);
        $this->reset('importFile');
        $this->resetPage();
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteVehicles =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::VEHICLES_WRITE);
    @endphp
    <flux:heading size="xl">{{ __('Vehicles') }}</flux:heading>

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Deleted :count records.', ['count' => session('bulk_deleted')]) }}
        </flux:callout>
    @endif

    @if (session()->has('import_created'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Imported rows: :count', ['count' => session('import_created')]) }}
        </flux:callout>
    @endif

    @if (session()->has('import_errors') && count(session('import_errors')) > 0)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading size="sm" class="mb-2">{{ __('Import errors') }}</flux:heading>
            <ul class="list-inside list-disc text-sm">
                @foreach (session('import_errors') as $err)
                    <li>{{ __('Row :row: :message', ['row' => $err['row'], 'message' => $err['message']]) }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total vehicles') }}</flux:text>
            <flux:heading size="xl">{{ $this->vehicleIndexStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Inspection within 30 days') }}</flux:text>
            <flux:heading size="xl">{{ $this->vehicleIndexStats['inspection_soon'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('With brand set') }}</flux:text>
            <flux:heading size="xl">{{ $this->vehicleIndexStats['with_brand'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('With model set') }}</flux:text>
            <flux:heading size="xl">{{ $this->vehicleIndexStats['with_model'] }}</flux:heading>
        </flux:card>
    </div>

    @if ($canWriteVehicles)
        @if ($editingVehicleId !== null)
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Edit vehicle') }}</flux:heading>
                <form wire:submit="updateVehicle" class="flex max-w-xl flex-col gap-4">
                    <flux:input wire:model="plate" :label="__('Plate')" required />
                    <flux:input wire:model="brand" :label="__('Brand')" />
                    <flux:input wire:model="model" :label="__('Model')" />
                    <flux:input wire:model="inspection_valid_until" type="date" :label="__('Inspection valid until')" />
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelVehicleEdit">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </flux:card>
        @else
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('New vehicle') }}</flux:heading>
                <form wire:submit="saveVehicle" class="flex max-w-xl flex-col gap-4">
                    <flux:input wire:model="plate" :label="__('Plate')" required />
                    <flux:input wire:model="brand" :label="__('Brand')" />
                    <flux:input wire:model="model" :label="__('Model')" />
                    <flux:input wire:model="inspection_valid_until" type="date" :label="__('Inspection valid until')" />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </form>
            </flux:card>
        @endif

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Import vehicles (CSV / Excel)') }}</flux:heading>
            <flux:text class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Headers: Plaka, Marka, Model, Muayene') }}
            </flux:text>
            <div class="flex max-w-xl flex-col gap-3">
                <flux:input wire:model="importFile" type="file" accept=".xlsx,.xls,.csv" />
                <flux:button type="button" wire:click="importVehicles" variant="ghost">{{ __('Import') }}</flux:button>
            </div>
        </flux:card>
    @endif

    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">{{ __('Advanced filters') }}</flux:heading>
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <flux:card class="!p-4">
                <flux:input
                    wire:model.live.debounce.400ms="filterSearch"
                    :label="__('Search (plate, brand, model)')"
                />
            </flux:card>
        @endif
    </div>

    @can(\App\Authorization\LogisticsPermission::ADMIN)
        @if (count($selectedIds) > 0)
            <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
                <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="bulkDeleteSelected"
                    wire:confirm="{{ __('Delete selected vehicles?') }}"
                >
                    {{ __('Delete selected') }}
                </flux:button>
            </div>
        @endif
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Fleet') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                @if ($canWriteVehicles)
                    <flux:table.column class="w-12">
                        <span class="sr-only">{{ __('Select page') }}</span>
                        <input
                            type="checkbox"
                            class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                            @checked($this->isPageFullySelected())
                            wire:click="toggleSelectPage"
                            wire:key="select-page-vehicles"
                        />
                    </flux:table.column>
                @endif
                @if ($canWriteVehicles)
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                @endif
                <flux:table.column>
                    <button type="button" wire:click="sortBy('id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('ID') }}
                        @if ($sortColumn === 'id')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('plate')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Plate') }}
                        @if ($sortColumn === 'plate')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('brand')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Brand') }}
                        @if ($sortColumn === 'brand')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('model')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Model') }}
                        @if ($sortColumn === 'model')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('inspection_valid_until')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Inspection') }}
                        @if ($sortColumn === 'inspection_valid_until')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedVehicles as $vehicle)
                    <flux:table.row :key="$vehicle->id">
                        @if ($canWriteVehicles)
                            <flux:table.cell>
                                <flux:checkbox wire:model.live="selectedIds" value="{{ $vehicle->id }}" />
                            </flux:table.cell>
                        @endif
                        @if ($canWriteVehicles)
                            <flux:table.cell>
                                <flux:button type="button" size="sm" variant="ghost" wire:click="startEdit({{ $vehicle->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $vehicle->id }}</flux:table.cell>
                        <flux:table.cell>{{ $vehicle->plate }}</flux:table.cell>
                        <flux:table.cell>{{ $vehicle->brand ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $vehicle->model ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $vehicle->inspection_valid_until?->format('Y-m-d') ?? '—' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canWriteVehicles ? 8 : 5 }}">{{ __('No vehicles yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedVehicles->links() }}
        </div>
    </flux:card>
</div>
