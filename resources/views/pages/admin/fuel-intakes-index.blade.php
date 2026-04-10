<?php

use App\Authorization\LogisticsPermission;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\FuelIntake;
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

new #[Lazy, Title('Fuel intakes')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;
    use WithPagination;

    public $importFile = null;

    public ?int $editingId = null;

    public string $vehicle_id = '';

    public string $liters = '';

    public string $odometer_km = '';

    public string $recorded_at = '';

    public string $filterSearch = '';

    public string $sortColumn = 'recorded_at';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var list<int> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', FuelIntake::class);
        $this->recorded_at = now()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{total: int, total_liters: float, intakes_this_month: int, avg_liters: float}
     */
    #[Computed(persist: true, seconds: 300)]
    public function fuelIntakeStats(): array
    {
        $monthStart = now()->startOfMonth()->toDateTimeString();

        $row = FuelIntake::query()
            ->toBase()
            ->selectRaw(
                'COUNT(*) as c, COALESCE(SUM(liters), 0) as sum_liters, COALESCE(AVG(liters), 0) as avg_liters'
            )
            ->first();

        $intakesThisMonth = FuelIntake::query()
            ->where('recorded_at', '>=', $monthStart)
            ->count();

        return [
            'total' => (int) ($row->c ?? 0),
            'total_liters' => (float) ($row->sum_liters ?? 0),
            'intakes_this_month' => $intakesThisMonth,
            'avg_liters' => (float) ($row->avg_liters ?? 0),
        ];
    }

    /**
     * @return Builder<FuelIntake>
     */
    private function intakesQuery(): Builder
    {
        $q = FuelIntake::query()->with('vehicle');

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->whereHas('vehicle', fn (Builder $vq) => $vq->where('plate', 'like', $term));
            });
        }

        $allowed = ['id', 'recorded_at', 'liters', 'vehicle_id'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'recorded_at';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    #[Computed]
    public function paginatedIntakes(): LengthAwarePaginator
    {
        return $this->intakesQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'recorded_at', 'liters', 'vehicle_id'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedIntakes->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedIntakes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $this->selectedIds);
        $allSelected = $pageIds !== [] && count(array_diff($pageIds, $selected)) === 0;

        if ($allSelected) {
            $this->selectedIds = array_values(array_diff($selected, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($selected, $pageIds)));
        }

        $this->selectedIds = array_values(array_map('intval', $this->selectedIds));
    }

    public function bulkDeleteSelected(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        Gate::authorize('viewAny', FuelIntake::class);

        if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $rows = FuelIntake::query()->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($rows as $row) {
            Gate::authorize('delete', $row);
            $row->delete();
            $deleted++;
        }

        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('bulk_deleted', $deleted);
    }

    public function startCreate(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        Gate::authorize('create', FuelIntake::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        $row = FuelIntake::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->editingId = $row->id;
        $this->vehicle_id = (string) $row->vehicle_id;
        $this->liters = (string) $row->liters;
        $this->odometer_km = $row->odometer_km !== null ? (string) $row->odometer_km : '';
        $this->recorded_at = $row->recorded_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);

        $validated = $this->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'liters' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'odometer_km' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'recorded_at' => ['required', 'date'],
        ]);

        $vehicle = Vehicle::query()->findOrFail((int) $validated['vehicle_id']);
        Gate::authorize('view', $vehicle);

        $data = [
            'vehicle_id' => (int) $validated['vehicle_id'],
            'liters' => $validated['liters'],
            'odometer_km' => $validated['odometer_km'] !== '' && $validated['odometer_km'] !== null
                ? $validated['odometer_km']
                : null,
            'recorded_at' => $validated['recorded_at'],
        ];

        if ($this->editingId !== null && $this->editingId > 0) {
            $row = FuelIntake::query()->findOrFail($this->editingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', FuelIntake::class);
            FuelIntake::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        $row = FuelIntake::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->vehicle_id = '';
        $this->liters = '';
        $this->odometer_km = '';
        $this->recorded_at = now()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
    }

    public function importFuelIntakes(ExcelImportService $excelImport): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        Gate::authorize('create', FuelIntake::class);

        $tenantId = auth()->user()?->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $stored = $this->importFile->store('imports', 'local');
        $path = Storage::disk('local')->path($stored);
        $result = $excelImport->importFuelIntakesFromPath($path, (int) $tenantId);
        Storage::disk('local')->delete($stored);

        session()->flash('import_created', $result['created']);
        session()->flash('import_errors', $result['errors']);
        $this->reset('importFile');
        $this->resetPage();
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteFuel =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::VEHICLES_WRITE);
    @endphp

    <x-admin.page-header
        :heading="__('Fuel intakes')"
        :description="__('Fleet fuel records for audit and anomaly checks. Import CSV/XLSX or add rows manually.')"
    >
        <x-slot name="actions">
            <flux:button :href="route('admin.vehicles.index')" variant="ghost" wire:navigate>{{ __('Vehicles') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total records') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->fuelIntakeStats['total']) }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total liters (tenant)') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->fuelIntakeStats['total_liters'], 3) }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Intakes this month') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->fuelIntakeStats['intakes_this_month']) }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Avg. liters per intake') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->fuelIntakeStats['avg_liters'], 2) }}</flux:heading>
        </flux:card>
    </div>

    <x-admin.filter-bar :label="__('Advanced filters')">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <flux:input wire:model.live.debounce.300ms="filterSearch" :label="__('Search by plate')" class="max-w-md" />
        @endif
    </x-admin.filter-bar>

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Deleted :count records.', ['count' => session('bulk_deleted')]) }}
        </flux:callout>
    @endif

    @if (session()->has('import_created'))
        <flux:callout variant="success">
            {{ __('Imported rows: :count', ['count' => session('import_created')]) }}
        </flux:callout>
    @endif

    @if (session()->has('import_errors') && count(session('import_errors')) > 0)
        <flux:callout variant="danger">
            <flux:heading size="sm" class="mb-2">{{ __('Import errors') }}</flux:heading>
            <ul class="list-inside list-disc text-sm">
                @foreach (session('import_errors') as $err)
                    <li>{{ __('Row :row: :msg', ['row' => $err['row'], 'msg' => $err['message']]) }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    @if ($canWriteFuel)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-2">{{ __('Import fuel intakes (CSV / Excel)') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Use the template headers: Plaka, Litre, Kilometre, Kayıt Tarihi. Vehicle plate must exist in this tenant.') }}
            </flux:text>
            <div class="flex flex-wrap items-end gap-4">
                <flux:input wire:model="importFile" type="file" accept=".xlsx,.xls,.csv" />
                <flux:button type="button" wire:click="importFuelIntakes" icon="arrow-up-tray" variant="primary">{{ __('Import') }}</flux:button>
                <flux:tooltip :content="__('Download template')" position="bottom">
                    <flux:button icon="document-arrow-down" :href="route('admin.fuel-intakes.template.xlsx')" variant="ghost" />
                </flux:tooltip>
            </div>
        </flux:card>
    @endif

    @if ($canWriteFuel)
        <flux:card class="p-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Add or edit record') }}</flux:heading>
                @if ($editingId === null)
                    <flux:button type="button" variant="primary" wire:click="startCreate">{{ __('New fuel intake') }}</flux:button>
                @endif
            </div>

            @if ($editingId !== null)
                <form wire:submit="save" class="grid max-w-xl gap-4">
                    <flux:select wire:model="vehicle_id" :label="__('Vehicle')" required>
                        <option value="">{{ __('Select vehicle') }}</option>
                        @foreach (\App\Models\Vehicle::query()->orderBy('plate')->get() as $v)
                            <option value="{{ $v->id }}">{{ $v->plate }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="liters" type="text" :label="__('Liters')" required />
                    <flux:input wire:model="odometer_km" type="text" :label="__('Odometer (km)')" />
                    <flux:input wire:model="recorded_at" type="datetime-local" :label="__('Recorded at')" required />
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @endif
        </flux:card>
    @endif

    @if ($canWriteFuel && count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button
                type="button"
                variant="danger"
                wire:click="bulkDeleteSelected"
                wire:confirm="{{ __('Delete selected fuel intakes?') }}"
            >
                {{ __('Delete selected') }}
            </flux:button>
        </div>
    @endif

    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        @if ($canWriteFuel)
                            <th class="w-12 py-2 pe-4">
                                <span class="sr-only">{{ __('Select page') }}</span>
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                                    @checked($this->isPageFullySelected())
                                    wire:click.prevent="toggleSelectPage"
                                    wire:key="select-page-intakes"
                                />
                            </th>
                        @endif
                        <th class="py-2 pe-4">
                            <button type="button" wire:click="sortBy('id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                                ID
                                @if ($sortColumn === 'id')
                                    <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="py-2 pe-4">
                            <button type="button" wire:click="sortBy('vehicle_id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                                {{ __('Vehicle') }}
                                @if ($sortColumn === 'vehicle_id')
                                    <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="py-2 pe-4">
                            <button type="button" wire:click="sortBy('liters')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                                {{ __('Liters') }}
                                @if ($sortColumn === 'liters')
                                    <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="py-2 pe-4">{{ __('Odometer') }}</th>
                        <th class="py-2 pe-4">
                            <button type="button" wire:click="sortBy('recorded_at')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                                {{ __('Recorded') }}
                                @if ($sortColumn === 'recorded_at')
                                    <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        @if ($canWriteFuel)
                            <th class="py-2 text-end">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedIntakes as $row)
                        <tr>
                            @if ($canWriteFuel)
                                <td class="py-2 pe-4">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                                        wire:key="intake-select-{{ $row->id }}"
                                        wire:model.live="selectedIds"
                                        value="{{ $row->id }}"
                                    />
                                </td>
                            @endif
                            <td class="py-2 pe-4 font-mono text-xs">{{ $row->id }}</td>
                            <td class="py-2 pe-4">{{ $row->vehicle?->plate ?? '—' }}</td>
                            <td class="py-2 pe-4">{{ number_format((float) $row->liters, 3) }}</td>
                            <td class="py-2 pe-4">{{ $row->odometer_km !== null ? number_format((float) $row->odometer_km, 2) : '—' }}</td>
                            <td class="py-2 pe-4 whitespace-nowrap">
                                {{ $row->recorded_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </td>
                            @if ($canWriteFuel)
                                <td class="py-2 text-end">
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $row->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="delete({{ $row->id }})"
                                        wire:confirm="{{ __('Delete this fuel intake?') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canWriteFuel ? 7 : 6 }}" class="py-6 text-center text-zinc-500">{{ __('No fuel intakes yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->paginatedIntakes->links() }}
        </div>
    </flux:card>
</div>
