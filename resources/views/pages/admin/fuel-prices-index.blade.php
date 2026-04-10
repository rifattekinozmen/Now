<?php

use App\Authorization\LogisticsPermission;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\FuelPrice;
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

new #[Lazy, Title('Fuel prices')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;
    use WithPagination;

    public $importFile = null;

    public ?int $editingId = null;

    public string $fuel_type = 'diesel';

    public string $price = '';

    public string $currency = 'TRY';

    public string $recorded_at = '';

    public string $source = '';

    public string $region = '';

    public string $filterSearch = '';

    public string $sortColumn = 'recorded_at';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var list<int> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', FuelPrice::class);
        $this->recorded_at = now()->timezone(config('app.timezone'))->format('Y-m-d');
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{total: int, prices_this_month: int, avg_price: float, fuel_types_count: int}
     */
    #[Computed]
    public function fuelPriceStats(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();

        $row = FuelPrice::query()
            ->toBase()
            ->selectRaw(
                'COUNT(*) as total, COALESCE(AVG(price), 0) as avg_price, COUNT(DISTINCT fuel_type) as fuel_types_count'
            )
            ->first();

        $pricesThisMonth = FuelPrice::query()
            ->where('recorded_at', '>=', $monthStart)
            ->count();

        return [
            'total' => (int) ($row->total ?? 0),
            'prices_this_month' => $pricesThisMonth,
            'avg_price' => (float) ($row->avg_price ?? 0),
            'fuel_types_count' => (int) ($row->fuel_types_count ?? 0),
        ];
    }

    /**
     * @return Builder<FuelPrice>
     */
    private function pricesQuery(): Builder
    {
        $q = FuelPrice::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('fuel_type', 'like', $term)
                    ->orWhere('source', 'like', $term)
                    ->orWhere('region', 'like', $term);
            });
        }

        $allowed = ['id', 'recorded_at', 'price', 'fuel_type', 'currency'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'recorded_at';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    #[Computed]
    public function paginatedPrices(): LengthAwarePaginator
    {
        return $this->pricesQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'recorded_at', 'price', 'fuel_type', 'currency'];
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
        $pageIds = $this->paginatedPrices->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedPrices->pluck('id')->map(fn ($id) => (int) $id)->all();
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
        Gate::authorize('viewAny', FuelPrice::class);

        if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $rows = FuelPrice::query()->whereIn('id', $ids)->get();
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
        Gate::authorize('create', FuelPrice::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        $row = FuelPrice::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->editingId = $row->id;
        $this->fuel_type = $row->fuel_type;
        $this->price = (string) $row->price;
        $this->currency = $row->currency;
        $this->recorded_at = $row->recorded_at->format('Y-m-d');
        $this->source = $row->source ?? '';
        $this->region = $row->region ?? '';
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
            'fuel_type'   => ['required', 'in:diesel,gasoline,lpg'],
            'price'       => ['required', 'numeric', 'min:0.0001', 'max:9999999'],
            'currency'    => ['required', 'in:TRY,EUR,USD'],
            'recorded_at' => ['required', 'date'],
            'source'      => ['nullable', 'string', 'max:100'],
            'region'      => ['nullable', 'string', 'max:100'],
        ]);

        $data = [
            'fuel_type'   => $validated['fuel_type'],
            'price'       => $validated['price'],
            'currency'    => $validated['currency'],
            'recorded_at' => $validated['recorded_at'],
            'source'      => $validated['source'] !== '' ? $validated['source'] : null,
            'region'      => $validated['region'] !== '' ? $validated['region'] : null,
        ];

        if ($this->editingId !== null && $this->editingId > 0) {
            $row = FuelPrice::query()->findOrFail($this->editingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', FuelPrice::class);
            FuelPrice::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        $row = FuelPrice::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->fuel_type = 'diesel';
        $this->price = '';
        $this->currency = 'TRY';
        $this->recorded_at = now()->timezone(config('app.timezone'))->format('Y-m-d');
        $this->source = '';
        $this->region = '';
    }

    public function importFuelPrices(ExcelImportService $excelImport): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        Gate::authorize('create', FuelPrice::class);

        $tenantId = auth()->user()?->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $stored = $this->importFile->store('imports', 'local');
        $path = Storage::disk('local')->path($stored);
        $result = $excelImport->importFuelPricesFromPath($path, (int) $tenantId);
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
        $canWrite =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::VEHICLES_WRITE);
    @endphp

    <x-admin.page-header
        :heading="__('Fuel prices')"
        :description="__('Market fuel price records per type and region. Import CSV/XLSX or add rows manually.')"
    >
        <x-slot name="actions">
            <flux:button :href="route('admin.fuel-intakes.index')" variant="ghost" wire:navigate>{{ __('Fuel intakes') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total records') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->fuelPriceStats['total']) }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Prices this month') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->fuelPriceStats['prices_this_month']) }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Avg. price') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->fuelPriceStats['avg_price'], 4) }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Fuel types') }}</flux:text>
            <flux:heading size="xl">{{ $this->fuelPriceStats['fuel_types_count'] }}</flux:heading>
        </flux:card>
    </div>

    <x-admin.filter-bar :label="__('Advanced filters')">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <flux:input wire:model.live.debounce.300ms="filterSearch" :label="__('Search by type / source / region')" class="max-w-md" />
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

    @if ($canWrite)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-2">{{ __('Import fuel prices (CSV / Excel)') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Template headers: Yakıt Tipi, Fiyat, Para Birimi, Kayıt Tarihi, Kaynak, Bölge') }}
            </flux:text>
            <div class="flex flex-wrap items-end gap-4">
                <flux:input wire:model="importFile" type="file" accept=".xlsx,.xls,.csv" />
                <flux:button type="button" wire:click="importFuelPrices" icon="arrow-up-tray" variant="primary">{{ __('Import') }}</flux:button>
                <flux:tooltip :content="__('Download template')" position="bottom">
                    <flux:button icon="document-arrow-down" :href="route('admin.fuel-prices.template.xlsx')" variant="ghost" />
                </flux:tooltip>
            </div>
        </flux:card>
    @endif

    @if ($canWrite)
        <flux:card class="p-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Add or edit record') }}</flux:heading>
                @if ($editingId === null)
                    <flux:button type="button" variant="primary" wire:click="startCreate">{{ __('New fuel price') }}</flux:button>
                @endif
            </div>

            @if ($editingId !== null)
                <form wire:submit="save" class="grid max-w-xl gap-4 sm:grid-cols-2">
                    <flux:select wire:model="fuel_type" :label="__('Fuel type')" required>
                        <option value="diesel">{{ __('Diesel') }}</option>
                        <option value="gasoline">{{ __('Gasoline') }}</option>
                        <option value="lpg">{{ __('LPG') }}</option>
                    </flux:select>
                    <flux:input wire:model="price" type="text" :label="__('Price')" required />
                    <flux:select wire:model="currency" :label="__('Currency')" required>
                        <option value="TRY">TRY</option>
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                    </flux:select>
                    <flux:input wire:model="recorded_at" type="date" :label="__('Recorded at')" required />
                    <flux:input wire:model="source" type="text" :label="__('Source')" class="sm:col-span-2" />
                    <flux:input wire:model="region" type="text" :label="__('Region')" class="sm:col-span-2" />
                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @endif
        </flux:card>
    @endif

    @if ($canWrite && count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button
                type="button"
                variant="danger"
                wire:click="bulkDeleteSelected"
                wire:confirm="{{ __('Delete selected fuel price records?') }}"
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
                        @if ($canWrite)
                            <th class="w-12 py-2 pe-4">
                                <span class="sr-only">{{ __('Select page') }}</span>
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                                    @checked($this->isPageFullySelected())
                                    wire:click.prevent="toggleSelectPage"
                                    wire:key="select-page-prices"
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
                            <button type="button" wire:click="sortBy('fuel_type')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                                {{ __('Type') }}
                                @if ($sortColumn === 'fuel_type')
                                    <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="py-2 pe-4">
                            <button type="button" wire:click="sortBy('price')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                                {{ __('Price') }}
                                @if ($sortColumn === 'price')
                                    <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="py-2 pe-4">
                            <button type="button" wire:click="sortBy('currency')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                                {{ __('Currency') }}
                                @if ($sortColumn === 'currency')
                                    <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="py-2 pe-4">{{ __('Source') }}</th>
                        <th class="py-2 pe-4">{{ __('Region') }}</th>
                        <th class="py-2 pe-4">
                            <button type="button" wire:click="sortBy('recorded_at')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                                {{ __('Recorded') }}
                                @if ($sortColumn === 'recorded_at')
                                    <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        @if ($canWrite)
                            <th class="py-2 text-end">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedPrices as $row)
                        <tr>
                            @if ($canWrite)
                                <td class="py-2 pe-4">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                                        wire:key="price-select-{{ $row->id }}"
                                        wire:model.live="selectedIds"
                                        value="{{ $row->id }}"
                                    />
                                </td>
                            @endif
                            <td class="py-2 pe-4 font-mono text-xs">{{ $row->id }}</td>
                            <td class="py-2 pe-4">{{ ucfirst($row->fuel_type) }}</td>
                            <td class="py-2 pe-4">{{ number_format((float) $row->price, 4) }}</td>
                            <td class="py-2 pe-4">{{ $row->currency }}</td>
                            <td class="py-2 pe-4">{{ $row->source ?? '—' }}</td>
                            <td class="py-2 pe-4">{{ $row->region ?? '—' }}</td>
                            <td class="py-2 pe-4 whitespace-nowrap">{{ $row->recorded_at->format('Y-m-d') }}</td>
                            @if ($canWrite)
                                <td class="py-2 text-end">
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $row->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="delete({{ $row->id }})"
                                        wire:confirm="{{ __('Delete this fuel price record?') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canWrite ? 9 : 8 }}" class="py-6 text-center text-zinc-500">{{ __('No fuel price records yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->paginatedPrices->links() }}
        </div>
    </flux:card>
</div>
