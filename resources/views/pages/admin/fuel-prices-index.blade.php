<?php

use App\Authorization\LogisticsPermission;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\FuelPrice;
use App\Services\Logistics\ExcelImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public string $archiveProvince = '';

    public string $archiveDistrict = '';

    public string $archiveStartDate = '';

    public string $archiveEndDate = '';

    /** @var list<array{id: string, name: string}> */
    public array $archiveCityOptions = [];

    /** @var list<array{id: string, name: string}> */
    public array $archiveDistrictOptions = [];

    /** @var list<array<string, mixed>> */
    public array $archiveApiRows = [];


    /** @var list<int> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', FuelPrice::class);
        $this->recorded_at = now()->timezone(config('app.timezone'))->format('Y-m-d');
        $this->archiveStartDate = now()->subDays(30)->format('Y-m-d');
        $this->archiveEndDate = now()->format('Y-m-d');
        $this->loadArchiveCityOptions();
        $this->loadArchiveDistrictOptions();
        $this->searchArchive();
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedArchiveProvince(): void
    {
        $this->loadArchiveDistrictOptions();
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

    public function searchArchive(): void
    {
        if (app()->runningUnitTests()) {
            $this->archiveApiRows = [];

            return;
        }

        $cityId = trim($this->archiveProvince);
        $countyId = trim($this->archiveDistrict);

        if ($cityId === '' || $countyId === '') {
            $this->archiveApiRows = [];

            return;
        }

        $start = $this->archiveStartDate !== '' ? $this->archiveStartDate : now()->subDays(30)->toDateString();
        $end = $this->archiveEndDate !== '' ? $this->archiveEndDate : now()->toDateString();

        $base = rtrim((string) config('totalenergies.archive_api_base_url', 'https://apimobile.guzelenerji.com.tr'), '/');

        try {
            $response = Http::timeout((int) config('totalenergies.timeout_seconds', 15))
                ->retry(2, 100)
                ->asJson()
                ->post($base.'/exapi/fuel_prices_by_date', [
                    'county_id' => (int) $countyId,
                    'start_date' => $start.'T00:00:00Z',
                    'end_date' => $end.'T23:59:59Z',
                ]);

            if (! $response->successful()) {
                $this->archiveApiRows = [];
                session()->flash('archive_error', __('Arşiv verisi alınamadı. Lütfen tekrar deneyin.'));

                return;
            }

            $payload = $response->json();
            $rows = is_array($payload) ? array_values(array_filter($payload, 'is_array')) : [];
            usort($rows, function (array $a, array $b): int {
                $dateA = isset($a['pricedate']) && is_string($a['pricedate']) ? $a['pricedate'] : '';
                $dateB = isset($b['pricedate']) && is_string($b['pricedate']) ? $b['pricedate'] : '';

                return strcmp($dateB, $dateA);
            });
            $this->archiveApiRows = $rows;
            session()->forget('archive_error');
        } catch (\Throwable) {
            $this->archiveApiRows = [];
            session()->flash('archive_error', __('Arşiv servisine erişilemedi.'));
        }
    }

    /**
     * @return array{
     *   day_count: int,
     *   latest_motorin: float|null,
     *   average_motorin: float|null,
     *   peak_daily_change_pct: float|null
     * }
     */
    #[Computed]
    public function archiveKpiStats(): array
    {
        $rows = $this->archiveApiRowsWithChanges;
        if ($rows === []) {
            return [
                'day_count' => 0,
                'latest_motorin' => null,
                'average_motorin' => null,
                'peak_daily_change_pct' => null,
            ];
        }

        $motorinValues = [];
        $peak = null;
        foreach ($rows as $row) {
            if (isset($row['motorin']) && is_numeric($row['motorin'])) {
                $motorinValues[] = (float) $row['motorin'];
            }

            if (isset($row['motorin_change_prev_pct']) && is_numeric($row['motorin_change_prev_pct'])) {
                $change = (float) $row['motorin_change_prev_pct'];
                if ($peak === null || abs($change) > abs($peak)) {
                    $peak = $change;
                }
            }
        }

        return [
            'day_count' => count($rows),
            'latest_motorin' => isset($rows[0]['motorin']) && is_numeric($rows[0]['motorin']) ? (float) $rows[0]['motorin'] : null,
            'average_motorin' => $motorinValues !== [] ? (array_sum($motorinValues) / count($motorinValues)) : null,
            'peak_daily_change_pct' => $peak,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function archiveApiRowsWithChanges(): array
    {
        $rows = array_values($this->archiveApiRows);
        $count = count($rows);

        for ($i = 0; $i < $count; $i++) {
            $current = isset($rows[$i]['motorin']) ? (float) $rows[$i]['motorin'] : null;
            $previous = ($i > 0 && isset($rows[$i - 1]['motorin'])) ? (float) $rows[$i - 1]['motorin'] : null;
            $next = ($i < $count - 1 && isset($rows[$i + 1]['motorin'])) ? (float) $rows[$i + 1]['motorin'] : null;

            $rows[$i]['motorin_change_prev_pct'] = null;
            $rows[$i]['motorin_change_next_pct'] = null;

            if ($current !== null && $previous !== null && abs($previous) > 0.000001) {
                $rows[$i]['motorin_change_prev_pct'] = (($current - $previous) / $previous) * 100;
            }

            if ($current !== null && $next !== null && abs($current) > 0.000001) {
                $rows[$i]['motorin_change_next_pct'] = (($next - $current) / $current) * 100;
            }
        }

        return $rows;
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

    private function loadArchiveCityOptions(): void
    {
        if (app()->runningUnitTests()) {
            $this->archiveCityOptions = [];

            return;
        }

        $base = rtrim((string) config('totalenergies.archive_api_base_url', 'https://apimobile.guzelenerji.com.tr'), '/');

        try {
            $response = Http::timeout((int) config('totalenergies.timeout_seconds', 15))
                ->retry(2, 100)
                ->get($base.'/exapi/fuel_price_cities');

            if (! $response->successful()) {
                $this->archiveCityOptions = [];

                return;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                $this->archiveCityOptions = [];

                return;
            }

            $cities = [];
            foreach ($payload as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = isset($item['city_id']) ? (string) $item['city_id'] : '';
                $name = isset($item['city_name']) && is_string($item['city_name']) ? $item['city_name'] : '';
                if ($id === '' || $name === '') {
                    continue;
                }

                $cities[] = ['id' => $id, 'name' => $name];
            }

            usort($cities, fn (array $a, array $b): int => strcmp(Str::ascii($a['name']), Str::ascii($b['name'])));
            $this->archiveCityOptions = $cities;
        } catch (\Throwable) {
            $this->archiveCityOptions = [];
        }

        if ($this->archiveCityOptions === []) {
            return;
        }

        $adana = collect($this->archiveCityOptions)->first(function (array $city): bool {
            return Str::upper(Str::ascii($city['name'])) === 'ADANA';
        });

        if (is_array($adana) && isset($adana['id'])) {
            $this->archiveProvince = (string) $adana['id'];
        } else {
            $selectedExists = collect($this->archiveCityOptions)->contains(
                fn (array $city): bool => $city['id'] === $this->archiveProvince
            );
            if (! $selectedExists) {
                $this->archiveProvince = $this->archiveCityOptions[0]['id'];
            }
        }
    }

    private function loadArchiveDistrictOptions(): void
    {
        if (app()->runningUnitTests()) {
            $this->archiveDistrictOptions = [];
            $this->archiveDistrict = '';

            return;
        }

        $cityId = trim($this->archiveProvince);
        if ($cityId === '') {
            $this->archiveDistrictOptions = [];
            $this->archiveDistrict = '';

            return;
        }

        $base = rtrim((string) config('totalenergies.archive_api_base_url', 'https://apimobile.guzelenerji.com.tr'), '/');

        try {
            $response = Http::timeout((int) config('totalenergies.timeout_seconds', 15))
                ->retry(2, 100)
                ->get($base.'/exapi/fuel_price_counties/'.$cityId, ['is_active' => 'true']);

            if (! $response->successful()) {
                $this->archiveDistrictOptions = [];
                $this->archiveDistrict = '';

                return;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                $this->archiveDistrictOptions = [];
                $this->archiveDistrict = '';

                return;
            }

            $districts = [];
            foreach ($payload as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = isset($item['county_id']) ? (string) $item['county_id'] : '';
                $name = isset($item['county_name']) && is_string($item['county_name']) ? $item['county_name'] : '';
                if ($id === '' || $name === '') {
                    continue;
                }

                $districts[] = ['id' => $id, 'name' => $name];
            }

            usort($districts, fn (array $a, array $b): int => strcmp(Str::ascii($a['name']), Str::ascii($b['name'])));
            $this->archiveDistrictOptions = $districts;
        } catch (\Throwable) {
            $this->archiveDistrictOptions = [];
        }

        if ($this->archiveDistrictOptions === []) {
            $this->archiveDistrict = '';

            return;
        }

        $merkez = collect($this->archiveDistrictOptions)->first(function (array $district): bool {
            return Str::upper(Str::ascii($district['name'])) === 'MERKEZ';
        });

        if (is_array($merkez) && isset($merkez['id'])) {
            $this->archiveDistrict = (string) $merkez['id'];
        } else {
            $selectedExists = collect($this->archiveDistrictOptions)->contains(
                fn (array $district): bool => $district['id'] === $this->archiveDistrict
            );
            if (! $selectedExists) {
                $this->archiveDistrict = $this->archiveDistrictOptions[0]['id'];
            }
        }
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

    public function updatedImportFile(): void
    {
        if ($this->importFile === null) {
            return;
        }

        try {
            $this->importFuelPrices(app(ExcelImportService::class));
        } catch (ValidationException $e) {
            $this->reset('importFile');
            throw $e;
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header
        :heading="__('Fuel prices')"
        :description="__('Market fuel price records per type and region. Import CSV/XLSX or add rows manually — full history and edit stay on this list page (no separate show route).')"
    >
        <x-slot name="actions">
            <x-admin.index-actions>
                <x-slot name="export">
                    <div class="flex items-center gap-2">
                        <flux:button
                            size="sm"
                            icon="document-arrow-down"
                            variant="outline"
                            :href="route('admin.fuel-prices.template.xlsx', ['city_id' => $archiveProvince, 'county_id' => $archiveDistrict, 'start_date' => $archiveStartDate, 'end_date' => $archiveEndDate])"
                        >
                            {{ __('Excel İndir') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            icon="document-text"
                            variant="outline"
                            :href="route('admin.fuel-prices.print', ['city_id' => $archiveProvince, 'county_id' => $archiveDistrict, 'start_date' => $archiveStartDate, 'end_date' => $archiveEndDate, 'mode' => 'pdf'])"
                            target="_blank"
                        >
                            {{ __('PDF') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            icon="printer"
                            variant="outline"
                            :href="route('admin.fuel-prices.print', ['city_id' => $archiveProvince, 'county_id' => $archiveDistrict, 'start_date' => $archiveStartDate, 'end_date' => $archiveEndDate, 'mode' => 'print'])"
                            target="_blank"
                        >
                            {{ __('Yazdır') }}
                        </flux:button>
                    </div>
                </x-slot>
            </x-admin.index-actions>
        </x-slot>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Arşiv gün sayısı') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->archiveKpiStats['day_count']) }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Son motorin (TL/Lt)') }}</flux:text>
            <flux:heading size="xl">
                {{ $this->archiveKpiStats['latest_motorin'] !== null ? number_format((float) $this->archiveKpiStats['latest_motorin'], 2) : '—' }}
            </flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Dönem ort. motorin (TL/Lt)') }}</flux:text>
            <flux:heading size="xl">
                {{ $this->archiveKpiStats['average_motorin'] !== null ? number_format((float) $this->archiveKpiStats['average_motorin'], 2) : '—' }}
            </flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('En yüksek günlük değişim') }}</flux:text>
            <flux:heading size="xl">
                {{ $this->archiveKpiStats['peak_daily_change_pct'] !== null ? (($this->archiveKpiStats['peak_daily_change_pct'] > 0 ? '+' : '').number_format((float) $this->archiveKpiStats['peak_daily_change_pct'], 2).'%') : '—' }}
            </flux:heading>
        </flux:card>
    </div>

    <flux:card class="overflow-hidden border-zinc-200 p-0 dark:border-zinc-700">
        <div class="flex items-center justify-between gap-3 bg-red-600 px-4 py-3 text-white">
            <flux:heading size="lg" class="text-white">{{ __('Akaryakıt Fiyatları') }}</flux:heading>
        </div>

        <div class="flex flex-nowrap gap-3 overflow-x-auto p-4">
            <div class="min-w-[200px] flex-1">
                <flux:select wire:model.live="archiveProvince" :label="__('İl')">
                    @foreach ($archiveCityOptions as $provinceOption)
                        <option value="{{ $provinceOption['id'] }}">{{ $provinceOption['name'] }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="min-w-[200px] flex-1">
                <flux:select wire:model.live="archiveDistrict" :label="__('İlçe')">
                    @foreach ($archiveDistrictOptions as $districtOption)
                        <option value="{{ $districtOption['id'] }}">{{ $districtOption['name'] }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="min-w-[200px] flex-1">
                <flux:input wire:model.live="archiveStartDate" type="date" :label="__('Başlangıç Tarihi')" />
            </div>

            <div class="min-w-[200px] flex-1">
                <flux:input wire:model.live="archiveEndDate" type="date" :label="__('Bitiş Tarihi')" />
            </div>

            <div class="flex min-w-[140px] flex-1 items-end">
                <flux:button class="h-10 w-full bg-red-600 hover:bg-red-700" variant="primary" wire:click="searchArchive">
                    {{ __('Ara') }}
                </flux:button>
            </div>
        </div>

        <div class="px-4 pb-4">
            <div class="mb-3 text-sm text-zinc-500">{{ __('Fiyatlara KDV Dahildir') }}</div>
            @if (session()->has('archive_error'))
                <flux:callout variant="danger" icon="exclamation-triangle" class="mb-3">
                    {{ session('archive_error') }}
                </flux:callout>
            @endif
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Tarih') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Excellium Kurşunsuz 95 TL/Lt') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Motorin TL/Lt') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Motorin Günlük Değişim (%)') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Excellium Motorin TL/Lt') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Kalorifer Yakıtı TL/Kg') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Fuel Oil TL/Kg') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Yüksek Kükürtlü Fuel Oil TL/Kg') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Otogaz TL/Lt') }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Gazyağı TL/Lt') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->archiveApiRowsWithChanges as $archiveRow)
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ isset($archiveRow['pricedate']) ? \Carbon\Carbon::parse($archiveRow['pricedate'])->translatedFormat('d F Y') : '—' }}
                                </td>
                                <td class="px-3 py-2">{{ isset($archiveRow['kursunsuz_95_excellium_95']) ? number_format((float) $archiveRow['kursunsuz_95_excellium_95'], 2) : '—' }}</td>
                                <td class="px-3 py-2">{{ isset($archiveRow['motorin']) ? number_format((float) $archiveRow['motorin'], 2) : '—' }}</td>
                                <td class="px-3 py-2">
                                    @php
                                        $prev = $archiveRow['motorin_change_prev_pct'] ?? null;

                                        $prevArrow = $prev === null ? '•' : ($prev > 0 ? '▲' : ($prev < 0 ? '▼' : '→'));

                                        $prevClass = $prev === null
                                            ? 'text-zinc-600 bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300'
                                            : ((float) abs($prev) <= 5
                                                ? 'text-zinc-700 bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-200'
                                                : ($prev > 0
                                                ? 'text-emerald-900 bg-emerald-200'
                                                : ($prev < 0
                                                    ? 'text-rose-800 bg-rose-200'
                                                    : 'text-zinc-700 bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-200')));

                                        $prevStyle = '';
                                        if ($prev !== null && (float) abs($prev) > 5) {
                                            $prevStyle = $prev > 0
                                                ? 'background-color:#34d399;color:#052e16;'
                                                : 'background-color:#fb7185;color:#4c0519;';
                                        }
                                    @endphp
                                    <div class="min-w-[104px] text-xs">
                                        <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-medium {{ $prevClass }}" style="{{ $prevStyle }}">
                                            <span class="text-sm leading-none">{{ $prevArrow }}</span>
                                            <span>
                                                {{ $prev !== null ? (($prev > 0 ? '+' : '').number_format((float) $prev, 2).'%') : '—' }}
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2">{{ isset($archiveRow['motorin_excellium']) ? number_format((float) $archiveRow['motorin_excellium'], 2) : '—' }}</td>
                                <td class="px-3 py-2">{{ isset($archiveRow['kalorifer_yakiti']) ? number_format((float) $archiveRow['kalorifer_yakiti'], 2) : '—' }}</td>
                                <td class="px-3 py-2">{{ isset($archiveRow['fuel_oil']) ? number_format((float) $archiveRow['fuel_oil'], 2) : '—' }}</td>
                                <td class="px-3 py-2">{{ isset($archiveRow['yuksek_kukurtlu_fuel_oil']) ? number_format((float) $archiveRow['yuksek_kukurtlu_fuel_oil'], 2) : '—' }}</td>
                                <td class="px-3 py-2">{{ isset($archiveRow['otogaz']) ? number_format((float) $archiveRow['otogaz'], 2) : '—' }}</td>
                                <td class="px-3 py-2">{{ isset($archiveRow['gazyagi']) ? number_format((float) $archiveRow['gazyagi'], 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-6 text-center text-zinc-500">{{ __('Bu filtrede kayıt bulunamadı.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </flux:card>

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
</div>
