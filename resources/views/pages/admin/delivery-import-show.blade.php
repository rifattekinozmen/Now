<?php

use App\Enums\DeliveryImportStatus;
use App\Models\DeliveryImport;
use App\Models\DeliveryImportPlateCorrection;
use App\Models\DeliveryImportRow;
use App\Services\Delivery\DeliveryReportImportService;
use App\Services\Delivery\DeliveryPlateCorrectionService;
use App\Services\Delivery\DeliveryReportPivotService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Delivery report detail')] class extends Component
{
    use WithPagination;

    public DeliveryImport $deliveryImport;

    /** excel = dosyadaki sol→sağ sütun sırası; schema = rapor şeması (config) sırası */
    public string $rowTableMode = 'excel';

    public string $pivotSortMetric = 'Teslimat miktarı';

    public string $klinkerMatchingOrderForm = 'petrokok_once';

    public string $petrokokRoutePreferenceForm = 'ekinciler';

    /** Satır tablosu sıralama: `row_index` veya `data:{json dizi indeksi}` */
    #[Url(history: true)]
    public string $rowSort = 'row_index';

    #[Url(history: true)]
    public string $rowSortDir = 'asc';

    #[Url(history: true)]
    public string $rowPlateFilter = '';

    public ?int $rowDetailExcelRowIndex = null;

    public ?int $rowDetailRowId = null;

    public string $rowDetailCurrentPlate = '';

    public string $plateCorrectionNewPlate = '';

    public string $plateCorrectionReason = '';

    public ?int $activePlateCorrectionRequestId = null;

    public string $plateCorrectionReviewNote = '';

    /**
     * @var array<int, array{label: string, value: string}>
     */
    public array $rowDetailItems = [];

    /**
     * Rapor satırı durumu rozeti (Beklemede / İşlendi / Hata) için kısa açıklama.
     */
    public function reportStatusHelp(): string
    {
        return match ($this->deliveryImport->status) {
            DeliveryImportStatus::Pending => __('Report status: pending help'),
            DeliveryImportStatus::Processed => __('Report status: processed help'),
            DeliveryImportStatus::Error => __('Report status: error help'),
        };
    }

    public function mount(DeliveryImport $deliveryImport): void
    {
        Gate::authorize('view', $deliveryImport);
        $this->deliveryImport = $deliveryImport;
        $types = config('delivery_report.report_types', []);
        $rt = $deliveryImport->report_type;
        if ($rt && isset($types[$rt]['pivot_metric_labels'])) {
            $labels = array_values($types[$rt]['pivot_metric_labels']);
            if ($labels !== []) {
                $this->pivotSortMetric = $labels[0];
            }
        }
        $this->klinkerMatchingOrderForm = $deliveryImport->klinker_matching_order;
        $this->petrokokRoutePreferenceForm = $deliveryImport->petrokok_route_preference;
    }

    public function updatedKlinkerMatchingOrderForm(): void
    {
        $this->persistImportMeta(['klinker_matching_order' => $this->klinkerMatchingOrderForm]);
    }

    public function updatedPetrokokRoutePreferenceForm(): void
    {
        $this->persistImportMeta(['petrokok_route_preference' => $this->petrokokRoutePreferenceForm]);
    }

    /**
     * @param  array<string, mixed>  $merge
     */
    protected function persistImportMeta(array $merge): void
    {
        Gate::authorize('update', $this->deliveryImport);
        $meta = $this->deliveryImport->meta ?? [];
        foreach ($merge as $k => $v) {
            $meta[$k] = $v;
        }
        $this->deliveryImport->update(['meta' => $meta]);
        $this->deliveryImport->refresh();
        unset($this->materialPivot);
    }

    public function updatedRowTableMode(): void
    {
        $this->rowSort = 'row_index';
        $this->rowSortDir = 'asc';
        $this->resetPage('diRows');
    }

    public function updatedRowPlateFilter(): void
    {
        $this->resetPage('diRows');
    }

    public function toggleRowSort(string $key): void
    {
        if ($this->rowSort === $key) {
            $this->rowSortDir = $this->rowSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->rowSort = $key;
            $this->rowSortDir = 'asc';
        }
        $this->resetPage('diRows');
    }

    public function openRowDetail(int $rowId): void
    {
        /** @var DeliveryImportRow $row */
        $row = DeliveryImportRow::query()
            ->where('delivery_import_id', $this->deliveryImport->id)
            ->findOrFail($rowId);

        $display = $this->formatRowForDisplay($row->row_data ?? []);
        $items = [];

        if ($this->useExcelColumnOrder) {
            foreach ($this->excelColumnLayout as $col) {
                $label = (string) ($col['header'] ?? '');
                $label = $label !== '' ? $label : '—';
                $excelNo = ((int) ($col['excel_col'] ?? 0)) + 1;
                $expectedIndex = $col['expected_index'] ?? null;
                $value = ($expectedIndex !== null) ? ((string) ($display[$expectedIndex] ?? '')) : '';
                $items[] = [
                    'label' => $excelNo.'.'.$label,
                    'value' => $value,
                ];
            }
        } else {
            foreach ($this->expectedHeaders as $idx => $label) {
                $n = $idx + 1;
                $items[] = [
                    'label' => $n.'.'.(string) $label,
                    'value' => (string) ($display[$idx] ?? ''),
                ];
            }
        }

        $this->rowDetailExcelRowIndex = (int) $row->row_index;
        $this->rowDetailRowId = (int) $row->id;
        $this->rowDetailItems = $items;
        $plateIdx = $this->resolvePlateIndexForImportedRows();
        $this->rowDetailCurrentPlate = $plateIdx !== null ? trim((string) (($row->row_data ?? [])[$plateIdx] ?? '')) : '';
        $this->plateCorrectionNewPlate = '';
        $this->plateCorrectionReason = '';

        $this->dispatch('modal-show', name: 'row-detail', scope: $this->getId());
    }

    public function openPlateCorrectionModal(): void
    {
        if (! $this->plateCorrectionFeatureEnabled) {
            return;
        }

        if ($this->rowDetailRowId === null) {
            return;
        }

        $this->plateCorrectionNewPlate = $this->rowDetailCurrentPlate;
        $this->plateCorrectionReason = '';
        $this->dispatch('modal-show', name: 'plate-correction', scope: $this->getId());
    }

    public function submitPlateCorrectionRequest(): void
    {
        if (! $this->plateCorrectionFeatureEnabled) {
            $this->addError('plateCorrectionNewPlate', __('Plaka düzeltme özelliği için migration çalıştırılmalıdır.'));

            return;
        }

        if ($this->rowDetailRowId === null) {
            return;
        }

        $newPlate = trim($this->plateCorrectionNewPlate);
        if ($newPlate === '') {
            $this->addError('plateCorrectionNewPlate', __('Yeni plaka boş olamaz.'));

            return;
        }

        /** @var DeliveryImportRow $row */
        $row = DeliveryImportRow::query()
            ->where('delivery_import_id', $this->deliveryImport->id)
            ->findOrFail($this->rowDetailRowId);

        app(DeliveryPlateCorrectionService::class)->createRequest(
            $this->deliveryImport,
            $row,
            $newPlate,
            $this->plateCorrectionReason !== '' ? $this->plateCorrectionReason : null,
            auth()->id()
        );

        $this->dispatch('modal-close', name: 'plate-correction', scope: $this->getId());
    }

    public function approvePlateCorrection(int $requestId): void
    {
        if (! $this->plateCorrectionFeatureEnabled) {
            return;
        }

        Gate::authorize('update', $this->deliveryImport);
        /** @var DeliveryImportPlateCorrection $request */
        $request = DeliveryImportPlateCorrection::query()
            ->where('delivery_import_id', $this->deliveryImport->id)
            ->findOrFail($requestId);

        app(DeliveryPlateCorrectionService::class)->approveRequest(
            $request,
            auth()->id(),
            $this->plateCorrectionReviewNote !== '' ? $this->plateCorrectionReviewNote : null
        );
        $this->activePlateCorrectionRequestId = null;
        $this->plateCorrectionReviewNote = '';
    }

    public function rejectPlateCorrection(int $requestId): void
    {
        if (! $this->plateCorrectionFeatureEnabled) {
            return;
        }

        Gate::authorize('update', $this->deliveryImport);
        /** @var DeliveryImportPlateCorrection $request */
        $request = DeliveryImportPlateCorrection::query()
            ->where('delivery_import_id', $this->deliveryImport->id)
            ->findOrFail($requestId);

        app(DeliveryPlateCorrectionService::class)->rejectRequest(
            $request,
            auth()->id(),
            $this->plateCorrectionReviewNote !== '' ? $this->plateCorrectionReviewNote : null
        );
        $this->activePlateCorrectionRequestId = null;
        $this->plateCorrectionReviewNote = '';
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function expectedHeaders(): array
    {
        return app(DeliveryReportImportService::class)->getExpectedHeadersForImport($this->deliveryImport);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function pivotRows(): array
    {
        $rows = app(DeliveryReportPivotService::class)->buildPivot($this->deliveryImport);
        $metric = $this->pivotSortMetric;
        usort($rows, function (array $a, array $b) use ($metric): int {
            $va = $a[$metric] ?? 0;
            $vb = $b[$metric] ?? 0;

            return $vb <=> $va;
        });

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function pivotMetricLabels(): array
    {
        $types = config('delivery_report.report_types', []);
        $rt = $this->deliveryImport->report_type;
        if (! $rt || ! isset($types[$rt]['pivot_metric_labels'])) {
            return ['Teslimat miktarı', 'Geçerli Miktar', 'Satır sayısı'];
        }

        return array_values($types[$rt]['pivot_metric_labels']);
    }

    /**
     * @return array{
     *     dates: array,
     *     materials: array<int, array{key: string, label: string}>,
     *     rows: array<int, mixed>,
     *     totals_row: array<string, mixed>,
     * }
     */
    #[Computed]
    public function materialPivot(): array
    {
        $plateFilter = trim($this->rowPlateFilter);
        $plateIndex = $this->resolvePlateIndexForImportedRows();

        return app(DeliveryReportPivotService::class)->buildMaterialPivot(
            $this->deliveryImport,
            $plateFilter !== '' ? $plateFilter : null,
            $plateIndex
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function pivotMesafeRows(): array
    {
        $types = config('delivery_report.report_types', []);
        $rt = $this->deliveryImport->report_type;
        if (! $rt || ! isset($types[$rt]['material_pivot'])) {
            return [];
        }
        $mp = $types[$rt]['material_pivot'];
        if (! isset($mp['pivot_mesafe_dimension_index'])) {
            return [];
        }
        $idx = (int) $mp['pivot_mesafe_dimension_index'];
        $label = (string) ($mp['pivot_mesafe_dimension_label'] ?? 'Varış');

        return app(DeliveryReportPivotService::class)->buildPivotForDimensionMap($this->deliveryImport, [$idx => $label]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function pivotMalzemeRows(): array
    {
        return app(DeliveryReportPivotService::class)->buildMalzemeCombinedPivot($this->deliveryImport);
    }

    #[Computed]
    public function reportTypeLabel(): ?string
    {
        $types = config('delivery_report.report_types', []);
        $rt = $this->deliveryImport->report_type;

        if (! $rt || ! isset($types[$rt]['label'])) {
            return null;
        }

        return $types[$rt]['label'];
    }

    #[Computed]
    public function materialPivotDayCount(): int
    {
        return count($this->materialPivot['rows'] ?? []);
    }

    #[Computed]
    public function materialPivotDateRangeText(): string
    {
        $rows = $this->materialPivot['rows'] ?? [];
        if ($rows === []) {
            return '';
        }
        $parsed = [];
        foreach ($rows as $r) {
            $t = $r['tarih'] ?? '';
            if ($t === '') {
                continue;
            }
            try {
                $parsed[] = Carbon::createFromFormat('d.m.Y', $t)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }
        if ($parsed === []) {
            return '';
        }
        usort($parsed, fn (Carbon $a, Carbon $b): int => $a <=> $b);
        $min = $parsed[0];
        $max = $parsed[count($parsed) - 1];
        if ($min->equalTo($max)) {
            return $min->format('d.m.Y');
        }

        return $min->format('d.m.Y').' – '.$max->format('d.m.Y');
    }

    #[Computed]
    public function rowsPaginator(): LengthAwarePaginator
    {
        $q = DeliveryImportRow::query()
            ->where('delivery_import_id', $this->deliveryImport->id);

        $this->applyImportedRowPlateFilter($q);
        $this->applyImportedRowSort($q);

        return $q->paginate(25, pageName: 'diRows');
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function rowPlateOptions(): array
    {
        $plateIdx = $this->resolvePlateIndexForImportedRows();
        if ($plateIdx === null) {
            return [];
        }

        $rows = DeliveryImportRow::query()
            ->where('delivery_import_id', $this->deliveryImport->id)
            ->orderBy('row_index')
            ->get(['row_data']);

        $set = [];
        foreach ($rows as $row) {
            $plate = trim((string) (($row->row_data ?? [])[$plateIdx] ?? ''));
            if ($plate !== '') {
                $set[$plate] = true;
            }
        }

        $plates = array_keys($set);
        usort($plates, function (string $a, string $b): int {
            return strcmp($this->normalizePlateForSort($a), $this->normalizePlateForSort($b));
        });

        return $plates;
    }

    /**
     * @return array<int, DeliveryImportPlateCorrection>
     */
    #[Computed]
    public function plateCorrectionRequests(): array
    {
        if (! $this->plateCorrectionFeatureEnabled) {
            return [];
        }

        $requests = DeliveryImportPlateCorrection::query()
            ->where('delivery_import_id', $this->deliveryImport->id)
            ->with(['requestedByUser:id,name', 'reviewedByUser:id,name'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->all();

        $selectedPlate = trim($this->rowPlateFilter);
        if ($selectedPlate === '') {
            return $requests;
        }

        $selectedNormalized = $this->normalizePlateForSort($selectedPlate);

        return array_values(array_filter($requests, function (DeliveryImportPlateCorrection $request) use ($selectedNormalized): bool {
            $oldPlate = $this->normalizePlateForSort((string) ($request->old_plate ?? ''));
            $newPlate = $this->normalizePlateForSort((string) ($request->new_plate ?? ''));

            return $oldPlate === $selectedNormalized || $newPlate === $selectedNormalized;
        }));
    }

    #[Computed]
    public function plateCorrectionFeatureEnabled(): bool
    {
        return Schema::hasTable('delivery_import_plate_corrections');
    }

    private function applyImportedRowPlateFilter(Builder $query): void
    {
        $selected = trim($this->rowPlateFilter);
        if ($selected === '') {
            return;
        }

        $plateIdx = $this->resolvePlateIndexForImportedRows();
        if ($plateIdx === null) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        $path = '$['.$plateIdx.']';
        $normalized = $this->normalizePlateForSort($selected);

        if ($driver === 'mysql') {
            $query->whereRaw(
                "
                UPPER(
                    REPLACE(
                        REPLACE(
                            TRIM(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?))),
                            ' ',
                            ''
                        ),
                        '-',
                        ''
                    )
                ) = ?
                ",
                [$path, $normalized]
            );

            return;
        }

        if ($driver === 'sqlite') {
            $query->whereRaw(
                "upper(replace(replace(trim(json_extract(row_data, ?)), ' ', ''), '-', '')) = ?",
                [$path, $normalized]
            );
        }
    }

    private function resolvePlateIndexForImportedRows(): ?int
    {
        $types = config('delivery_report.report_types', []);
        $rt = $this->deliveryImport->report_type;
        $reportConfig = $rt ? ($types[$rt] ?? []) : [];
        $vehicleMatching = $reportConfig['material_pivot']['vehicle_matching'] ?? null;

        if (is_array($vehicleMatching) && isset($vehicleMatching['plate_index'])) {
            return (int) $vehicleMatching['plate_index'];
        }

        $dimensions = $reportConfig['pivot_dimensions'] ?? [];
        $plakaIndex = array_search('Plaka', $dimensions, true);

        return $plakaIndex === false ? null : (int) $plakaIndex;
    }

    private function normalizePlateForSort(string $plate): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($plate)));
    }

    private function applyImportedRowSort(Builder $query): void
    {
        $sort = $this->rowSort;
        $dir = strtolower($this->rowSortDir) === 'desc' ? 'desc' : 'asc';

        if ($sort === '' || $sort === 'row_index') {
            $types = config('delivery_report.report_types', []);
            $rt = $this->deliveryImport->report_type;
            $reportConfig = $rt ? ($types[$rt] ?? []) : [];
            $vehicleMatching = $reportConfig['material_pivot']['vehicle_matching'] ?? null;
            $driver = DB::connection()->getDriverName();

            if (is_array($vehicleMatching)
                && isset($vehicleMatching['main_date_index'], $vehicleMatching['entry_date_index'], $vehicleMatching['entry_time_index'], $vehicleMatching['exit_date_index'], $vehicleMatching['exit_time_index'], $vehicleMatching['plate_index'])) {
                $mainDatePath = '$['.(int) $vehicleMatching['main_date_index'].']';
                $entryDatePath = '$['.(int) $vehicleMatching['entry_date_index'].']';
                $entryTimePath = '$['.(int) $vehicleMatching['entry_time_index'].']';
                $exitDatePath = '$['.(int) $vehicleMatching['exit_date_index'].']';
                $exitTimePath = '$['.(int) $vehicleMatching['exit_time_index'].']';
                $platePath = '$['.(int) $vehicleMatching['plate_index'].']';

                if ($driver === 'mysql') {
                    $query->orderByRaw(
                        "
                        UPPER(
                            REPLACE(
                                REPLACE(
                                    TRIM(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?))),
                                    ' ',
                                    ''
                                ),
                                '-',
                                ''
                            )
                        ) COLLATE utf8mb4_unicode_ci {$dir},
                        STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), '%d.%m.%Y') {$dir},
                        STR_TO_DATE(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), ''), JSON_UNQUOTE(JSON_EXTRACT(row_data, ?))), '%d.%m.%Y') {$dir},
                        COALESCE(
                            STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), '%H:%i:%s'),
                            STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), '%H:%i'),
                            STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), '%r')
                        ) {$dir},
                        STR_TO_DATE(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), ''), JSON_UNQUOTE(JSON_EXTRACT(row_data, ?))), '%d.%m.%Y') {$dir},
                        COALESCE(
                            STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), '%H:%i:%s'),
                            STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), '%H:%i'),
                            STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), '%r')
                        ) {$dir},
                        UPPER(
                            REPLACE(
                                REPLACE(
                                    TRIM(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?))),
                                    ' ',
                                    ''
                                ),
                                '-',
                                ''
                            )
                        ) COLLATE utf8mb4_unicode_ci {$dir},
                        row_index asc
                        ",
                        [
                            $platePath,
                            $mainDatePath,
                            $entryDatePath, $mainDatePath,
                            $entryTimePath, $entryTimePath, $entryTimePath,
                            $exitDatePath, $mainDatePath,
                            $exitTimePath, $exitTimePath, $exitTimePath,
                            $platePath,
                        ]
                    );

                    return;
                }

                if ($driver === 'sqlite') {
                    $query->orderByRaw(
                        'json_extract(row_data, ?) COLLATE NOCASE '.$dir.',
                         json_extract(row_data, ?) COLLATE NOCASE '.$dir.',
                         json_extract(row_data, ?) COLLATE NOCASE '.$dir.',
                         json_extract(row_data, ?) COLLATE NOCASE '.$dir.',
                         json_extract(row_data, ?) COLLATE NOCASE '.$dir.',
                         json_extract(row_data, ?) COLLATE NOCASE '.$dir.',
                         row_index asc',
                        [$platePath, $mainDatePath, $entryDatePath, $entryTimePath, $exitDatePath, $exitTimePath]
                    );

                    return;
                }
            }

            $query->orderBy('row_index', $dir);

            return;
        }

        if (! str_starts_with($sort, 'data:')) {
            $query->orderBy('row_index', 'asc');

            return;
        }

        $idx = (int) substr($sort, 5);
        if ($idx < 0 || $idx > 2048) {
            $query->orderBy('row_index', 'asc');

            return;
        }

        $path = '$['.$idx.']';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->orderByRaw('json_extract(row_data, ?) COLLATE NOCASE '.$dir.', row_index asc', [$path]);
        } elseif ($driver === 'mysql') {
            $query->orderByRaw('JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) '.$dir.', row_index asc', [$path]);
        } else {
            $query->orderBy('row_index', 'asc');
        }
    }

    /**
     * @return array<int, array{excel_col: int, header: string, expected_index: int|null}>
     */
    #[Computed]
    public function excelColumnLayout(): array
    {
        return app(DeliveryReportImportService::class)->getExcelColumnLayoutForDisplay($this->deliveryImport);
    }

    #[Computed]
    public function useExcelColumnOrder(): bool
    {
        return $this->rowTableMode === 'excel' && count($this->excelColumnLayout) > 0;
    }

    /**
     * @param  array<int, mixed>  $rowData
     * @return array<int, string>
     */
    public function formatRowForDisplay(array $rowData): array
    {
        return app(DeliveryReportImportService::class)->formatRowDataForDisplay($this->deliveryImport, $rowData);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex w-full flex-wrap items-center justify-between gap-3">
        <flux:button :href="route('admin.delivery-imports.index')" variant="ghost" wire:navigate icon="arrow-left" size="sm">
            {{ __('Delivery Reports') }}
        </flux:button>
        <flux:tooltip :content="$this->reportStatusHelp()" position="bottom">
            <span class="inline-flex cursor-help" tabindex="0">
                <flux:badge color="{{ $deliveryImport->status->color() }}" size="sm">{{ $deliveryImport->status->label() }}</flux:badge>
            </span>
        </flux:tooltip>
    </div>

    <x-admin.page-header
        :heading="$deliveryImport->reference_no ?? __('Report #:id', ['id' => $deliveryImport->id])"
        :description="__('Excel rows, pivot summary, and tonnage sort.')"
    >
        <x-slot name="actions">
            <flux:text class="text-sm text-zinc-500">
                {{ $deliveryImport->import_date->format('d M Y') }}
                · {{ __(':n rows', ['n' => number_format($deliveryImport->row_count)]) }}
            </flux:text>
        </x-slot>
    </x-admin.page-header>

    @php
        $deliveryPhpIni = php_ini_loaded_file();
        $zipUnavailable = ! \App\Support\DeliveryImportPhp::isZipAvailableForXlsx();
        $le = (string) ($deliveryImport->last_error ?? '');
        $lastErrorLooksLikeZipOnly = $le !== '' && (
            str_contains($le, 'ZipArchive')
            || str_contains($le, 'zip eklentisi')
            || str_contains($le, 'PHP zip')
        );
    @endphp
    @if ($zipUnavailable)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Web sunucusunda PHP zip kapalı') }}</flux:callout.heading>
            <flux:callout.text class="mt-2 space-y-2 text-sm">
                <p>{{ __('.xlsx / .xlsm için ZipArchive gerekir. Aşağıdaki php.ini içinde extension=zip açık olsun; Laragon’da Stop All → Start All.') }}</p>
                @if ($deliveryPhpIni)
                    <p class="font-mono text-xs text-zinc-400">{{ $deliveryPhpIni }}</p>
                @endif
                <p class="text-zinc-300">{{ __('Geçici çözüm: Excel’de dosyayı “Excel 97-2003 Çalışma Kitabı (.xls)” olarak kaydedip aynı içerikle tekrar içe aktarın — .xls için zip gerekmez.') }}</p>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($deliveryImport->last_error && ! ($zipUnavailable && $lastErrorLooksLikeZipOnly))
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text class="whitespace-pre-wrap text-sm">{{ \Illuminate\Support\Str::limit($deliveryImport->last_error, 2000) }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Pivot: malzeme matrisi (Tarih × malzeme) veya boyut/metrik özeti --}}
    <flux:card class="overflow-hidden bg-white p-0 dark:bg-zinc-900 sm:p-4">
        <div class="border-b border-zinc-200 p-4 dark:border-zinc-700 sm:border-0 sm:p-0 sm:pb-4">
            @if (! empty($this->materialPivot['rows']))
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        <flux:heading size="lg" class="font-bold text-slate-900 dark:text-zinc-50">
                            {{ __(':n-Day Data Analysis Report', ['n' => $this->materialPivotDayCount]) }}
                        </flux:heading>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($this->reportTypeLabel)
                                <flux:badge color="sky" size="sm" class="rounded-full border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-200">{{ $this->reportTypeLabel }}</flux:badge>
                            @endif
                            @if ($this->materialPivotDateRangeText !== '')
                                <span class="text-sm text-slate-500 dark:text-zinc-400">{{ str_replace(' – ', '—', $this->materialPivotDateRangeText) }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-shrink-0 flex-wrap items-center justify-end gap-2">
                        <flux:button size="sm" variant="outline" icon="arrow-down-tray" class="border-[#e0e0e0] text-slate-800 dark:border-zinc-600 dark:text-zinc-200" :href="route('admin.delivery-imports.analysis.xlsx', ['deliveryImport' => $deliveryImport, 'plate' => $rowPlateFilter !== '' ? $rowPlateFilter : null])">
                            {{ __('Analysis Excel') }}
                        </flux:button>
                        <flux:button size="sm" variant="outline" icon="bars-3" class="border-[#e0e0e0] text-slate-800 dark:border-zinc-600 dark:text-zinc-200" :href="route('admin.delivery-imports.index')" wire:navigate>
                            {{ __('Back to list') }}
                        </flux:button>
                    </div>
                </div>
                <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ __('The pivot table shows date rows and material columns. Each cell shows valid quantity totals (t / count). The colored columns on the right show empty–full and full–full valid quantities.') }}</flux:text>
            @else
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <flux:heading size="lg">{{ __('Pivot summary') }}</flux:heading>
                    @if (count($this->pivotRows) > 0)
                        <flux:select wire:model.live="pivotSortMetric" :label="__('Sort by tonnage / metric')" class="max-w-xs">
                            @foreach ($this->pivotMetricLabels as $label)
                                <option value="{{ $label }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>
            @endif
        </div>

        @if (! empty($this->materialPivot['rows']))
            @php
                $mp = $this->materialPivot;
                $materials = $mp['materials'] ?? [];
                $pivotRowsData = $mp['rows'] ?? [];
                $totalsRow = $mp['totals_row'] ?? [];
                $pivotHeadBg     = 'bg-[#e7f1ff] dark:bg-zinc-800';
                $pivotBorderCell = '[&_td]:border-[#e0e0e0] [&_th]:border-[#e0e0e0] dark:[&_td]:border-zinc-600 dark:[&_th]:border-zinc-600';
                $pivotFooterBg   = 'bg-[#e7f1ff] dark:bg-zinc-800';
                $pivotFooterLead = 'bg-[#cfe2ff] text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100';
                $pivotTotalColBg = 'bg-[#f0f4f8] dark:bg-zinc-800';
                $pivotColBosDolu       = 'bg-[#cfe2ff] text-blue-700 dark:bg-blue-900 dark:text-blue-100';
                $pivotColDoluDolu      = 'bg-[#d1e7dd] text-[#0f5132] dark:bg-green-900 dark:text-green-100';
                $pivotColMalzemeKisa      = 'bg-[#f8f9fa] text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200';
                /** Sütun genişlikleri (%): malzeme sütunlarına kalan pay eşit bölünür */
                $pivotMatCount = count($materials);
                $pivotColDatePct = 6;
                $pivotColGrandPct = 16;
                $pivotColBosPct = 7;
                $pivotColDoluPct = 7;
                $pivotColKisaPct = 10;
                $pivotTailPct = $pivotColGrandPct + $pivotColBosPct + $pivotColDoluPct + $pivotColKisaPct;
                $pivotMatBucket = max(0, 100 - $pivotColDatePct - $pivotTailPct);
                if ($pivotMatCount === 0) {
                    $pivotColKisaPct += $pivotMatBucket;
                    $pivotEachMatPct = 0;
                    $pivotMatExtraOne = 0;
                } else {
                    $pivotEachMatPct = intdiv($pivotMatBucket, $pivotMatCount);
                    $pivotMatExtraOne = $pivotMatBucket % $pivotMatCount;
                }
            @endphp
            <div class="not-prose w-full overflow-hidden rounded-xl border border-[#e0e0e0] bg-white text-slate-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                <style>
                    /* Renkleri derleme/cache'den bağımsız zorla uygula */
                    .veri-analiz-pivot {
                        font-size: 12px !important;
                        table-layout: fixed !important;
                        width: 100% !important;
                    }
                    .veri-analiz-pivot th.mat-col .mat-col-head,
                    .veri-analiz-pivot td.mat-col {
                        min-width: 0 !important;
                        overflow-wrap: anywhere;
                        word-break: break-word;
                    }
                    .veri-analiz-pivot,
                    .veri-analiz-pivot * {
                        font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif !important;
                        -webkit-font-smoothing: antialiased;
                        -moz-osx-font-smoothing: grayscale;
                    }
                    .veri-analiz-pivot th,
                    .veri-analiz-pivot td {
                        padding: 4px 6px !important;
                        border: 1px solid #cbd5e1 !important;
                    }
                    .veri-analiz-pivot {
                        border-collapse: collapse !important;
                        border: 1px solid #94a3b8 !important;
                    }
                    .dark .veri-analiz-pivot th,
                    .dark .veri-analiz-pivot td { border-color: #52525b !important; }
                    .dark .veri-analiz-pivot { border-color: #71717a !important; }
                    .veri-analiz-pivot thead th {
                        font-size: 11px !important;
                        line-height: 1.2 !important;
                        font-weight: 700 !important;
                    }
                    .veri-analiz-pivot tbody th {
                        font-size: 12px !important;
                        line-height: 1.3 !important;
                        font-weight: 400 !important;
                    }
                    .veri-analiz-pivot tbody tr:not(:last-child) > th:first-child {
                        font-weight: 500 !important;
                    }
                    .veri-analiz-pivot tbody tr:last-child > th:first-child {
                        font-weight: 700 !important;
                    }
                    .veri-analiz-pivot td {
                        font-size: 12px !important;
                        line-height: 1.3 !important;
                        font-weight: 400 !important;
                    }
                    .veri-analiz-pivot tbody tr:last-child td {
                        font-weight: 700 !important;
                    }
                    .veri-analiz-pivot td span,
                    .veri-analiz-pivot th span { font-size: inherit !important; line-height: inherit !important; }
                    /* Tailwind sınıfları: satır tipine göre (ekrandaki gibi gövde normal, başlık kalın, toplam satırı daha kalın) */
                    .veri-analiz-pivot thead .font-bold { font-weight: 700 !important; }
                    .veri-analiz-pivot thead .font-semibold { font-weight: 600 !important; }
                    .veri-analiz-pivot thead .font-medium { font-weight: 600 !important; }
                    .veri-analiz-pivot tbody tr:not(:last-child) .font-bold,
                    .veri-analiz-pivot tbody tr:not(:last-child) .font-semibold,
                    .veri-analiz-pivot tbody tr:not(:last-child) .font-medium { font-weight: 400 !important; }
                    .veri-analiz-pivot tbody tr:last-child .font-bold,
                    .veri-analiz-pivot tbody tr:last-child .font-semibold,
                    .veri-analiz-pivot tbody tr:last-child .font-medium { font-weight: 700 !important; }
                    .veri-analiz-pivot .tabular-nums { letter-spacing: -0.1px; }
                    /* Malzeme sütunları: tek sakin ton (yan yana farklı renk yok) */
                    .veri-analiz-pivot tbody tr:not(:last-child) td.mat-col {
                        font-weight: 400 !important;
                        color: #475569 !important;
                        line-height: 1.38 !important;
                    }
                    .veri-analiz-pivot thead th.mat-col .mat-col-code {
                        color: #334155 !important;
                        font-weight: 700 !important;
                    }
                    .veri-analiz-pivot thead th.mat-col span:not(.mat-col-code):not(.mat-col-firma) {
                        font-weight: 400 !important;
                    }
                    .veri-analiz-pivot thead th.mat-col .mat-col-firma {
                        color: #6b21a8 !important;
                        font-weight: 600 !important;
                    }
                    .dark .veri-analiz-pivot thead th.mat-col .mat-col-firma {
                        color: #d8b4fe !important;
                    }
                    /* TOPLAM sütunu: malzeme sütunlarından daha kalın */
                    .veri-analiz-pivot tbody tr:not(:last-child) td.pivot-col-total,
                    .veri-analiz-pivot tbody tr:not(:last-child) td.pivot-col-total span {
                        font-weight: 700 !important;
                    }
                    .veri-analiz-pivot .pivot-col-total .pivot-total-metric {
                        white-space: nowrap !important;
                    }
                    .dark .veri-analiz-pivot thead th.mat-col .mat-col-code { color: #e2e8f0 !important; }
                    .veri-analiz-pivot thead th { background-color: #e7f1ff !important; }
                    .veri-analiz-pivot thead th:nth-last-child(4),
                    .veri-analiz-pivot tbody td:nth-last-child(4) { background-color: #f0f4f8 !important; color: #1f2937 !important; }
                    .veri-analiz-pivot thead th:nth-last-child(3),
                    .veri-analiz-pivot tbody td:nth-last-child(3) { background-color: #cfe2ff !important; color: #1d4ed8 !important; }
                    .veri-analiz-pivot thead th:nth-last-child(2),
                    .veri-analiz-pivot tbody td:nth-last-child(2) { background-color: #d1e7dd !important; color: #0f5132 !important; }
                    .veri-analiz-pivot thead th:last-child,
                    .veri-analiz-pivot tbody td:last-child { background-color: #f8f9fa !important; color: #374151 !important; }
                    .veri-analiz-pivot tbody tr:last-child th { background-color: #cfe2ff !important; color: #111827 !important; }
                    .veri-analiz-pivot tbody tr:last-child td { background-color: #e7f1ff !important; }
                    .veri-analiz-pivot tbody tr:last-child td:nth-last-child(4) { background-color: #cfe2ff !important; color: #111827 !important; }
                    .veri-analiz-pivot tbody tr:last-child td:nth-last-child(3) { background-color: #cfe2ff !important; color: #1d4ed8 !important; }
                    .veri-analiz-pivot tbody tr:last-child td:nth-last-child(2) { background-color: #d1e7dd !important; color: #0f5132 !important; }
                    .veri-analiz-pivot tbody tr:last-child td:last-child { background-color: #f8f9fa !important; color: #374151 !important; }
                    .veri-analiz-pivot tbody tr:last-child td.mat-col { color: #1e293b !important; }
                    .dark .veri-analiz-pivot tbody tr:last-child td.mat-col { color: #f1f5f9 !important; }
                    /* Dark mod: açık zemin !important kurallarını koyu paletle eşle (TARİH vb. kontrast) */
                    .dark .veri-analiz-pivot thead th {
                        background-color: #3f3f46 !important;
                        color: #fafafa !important;
                    }
                    .dark .veri-analiz-pivot thead th:nth-last-child(4) {
                        background-color: #52525b !important;
                        color: #fafafa !important;
                    }
                    .dark .veri-analiz-pivot thead th:nth-last-child(3) {
                        background-color: #1e3a8a !important;
                        color: #bfdbfe !important;
                    }
                    .dark .veri-analiz-pivot thead th:nth-last-child(2) {
                        background-color: #166534 !important;
                        color: #bbf7d0 !important;
                    }
                    .dark .veri-analiz-pivot thead th:last-child {
                        background-color: #3f3f46 !important;
                        color: #e4e4e7 !important;
                    }
                    .dark .veri-analiz-pivot thead th.mat-col span:not(.mat-col-code):not(.mat-col-firma) {
                        color: #a1a1aa !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:not(:last-child) > th:first-child {
                        background-color: #18181b !important;
                        color: #e4e4e7 !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:not(:last-child) td.mat-col {
                        background-color: #18181b !important;
                        color: #cbd5e1 !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:not(:last-child) td:nth-last-child(4) {
                        background-color: #3f3f46 !important;
                        color: #fafafa !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:not(:last-child) td:nth-last-child(3) {
                        background-color: #1e3a8a !important;
                        color: #93c5fd !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:not(:last-child) td:nth-last-child(2) {
                        background-color: #14532d !important;
                        color: #86efac !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:not(:last-child) td:last-child {
                        background-color: #27272a !important;
                        color: #d4d4d8 !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:last-child th {
                        background-color: #52525b !important;
                        color: #fafafa !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:last-child td {
                        background-color: #3f3f46 !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:last-child td.mat-col {
                        background-color: #3f3f46 !important;
                        color: #f1f5f9 !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:last-child td:nth-last-child(4) {
                        background-color: #52525b !important;
                        color: #fafafa !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:last-child td:nth-last-child(3) {
                        background-color: #1e40af !important;
                        color: #dbeafe !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:last-child td:nth-last-child(2) {
                        background-color: #166534 !important;
                        color: #dcfce7 !important;
                    }
                    .dark .veri-analiz-pivot tbody tr:last-child td:last-child {
                        background-color: #3f3f46 !important;
                        color: #e4e4e7 !important;
                    }
                    .dark .veri-analiz-pivot th,
                    .dark .veri-analiz-pivot td {
                        border-color: #64748b !important;
                    }
                    .dark .veri-analiz-pivot {
                        border-color: #64748b !important;
                    }
                    /*
                     * Satır hover: nth-child zeminleri !important olduğu için Tailwind group-hover eziliyordu.
                     * Veri satırlarında tüm hücrelerde hover tonu + hafif “pencere” çerçevesi.
                     */
                    .veri-analiz-pivot tbody tr.group:not(:last-child):hover > th:first-child {
                        background-color: #f4f4f5 !important;
                        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.45) !important;
                    }
                    .veri-analiz-pivot tbody tr.group:not(:last-child):hover td.mat-col {
                        background-color: #f4f4f5 !important;
                        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.45) !important;
                    }
                    .veri-analiz-pivot tbody tr.group:not(:last-child):hover td:nth-last-child(4) {
                        background-color: #e2e8f0 !important;
                        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.45) !important;
                    }
                    .veri-analiz-pivot tbody tr.group:not(:last-child):hover td:nth-last-child(3) {
                        background-color: #bfdbfe !important;
                        box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.35) !important;
                    }
                    .veri-analiz-pivot tbody tr.group:not(:last-child):hover td:nth-last-child(2) {
                        background-color: #bbf7d0 !important;
                        box-shadow: inset 0 0 0 1px rgba(22, 163, 74, 0.35) !important;
                    }
                    .veri-analiz-pivot tbody tr.group:not(:last-child):hover td:last-child {
                        background-color: #e4e4e7 !important;
                        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.45) !important;
                    }
                    .dark .veri-analiz-pivot tbody tr.group:not(:last-child):hover > th:first-child {
                        background-color: #27272a !important;
                        box-shadow: inset 0 0 0 1px rgba(113, 113, 122, 0.55) !important;
                    }
                    .dark .veri-analiz-pivot tbody tr.group:not(:last-child):hover td.mat-col {
                        background-color: #27272a !important;
                        box-shadow: inset 0 0 0 1px rgba(113, 113, 122, 0.55) !important;
                    }
                    .dark .veri-analiz-pivot tbody tr.group:not(:last-child):hover td:nth-last-child(4) {
                        background-color: #3f3f46 !important;
                        box-shadow: inset 0 0 0 1px rgba(113, 113, 122, 0.55) !important;
                    }
                    .dark .veri-analiz-pivot tbody tr.group:not(:last-child):hover td:nth-last-child(3) {
                        background-color: #1e3a8a !important;
                        box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.4) !important;
                    }
                    .dark .veri-analiz-pivot tbody tr.group:not(:last-child):hover td:nth-last-child(2) {
                        background-color: #14532d !important;
                        box-shadow: inset 0 0 0 1px rgba(74, 222, 128, 0.35) !important;
                    }
                    .dark .veri-analiz-pivot tbody tr.group:not(:last-child):hover td:last-child {
                        background-color: #3f3f46 !important;
                        box-shadow: inset 0 0 0 1px rgba(113, 113, 122, 0.55) !important;
                    }
                </style>
                <div class="overflow-x-auto rounded-[inherit]">
                <table class="veri-analiz-pivot w-full min-w-full border-collapse text-[0.58rem] leading-tight [&_td]:box-border [&_th]:box-border [&_td]:border [&_th]:border [&_td]:border-solid [&_th]:border-solid {{ $pivotBorderCell }}">
                    <caption class="sr-only">{{ __('Tarih ve malzeme bazında teslimat özeti') }}</caption>
                    <colgroup>
                        <col style="width: {{ $pivotColDatePct }}%;" />
                        @foreach ($materials as $_m)
                            @php
                                $pivotMatColPct = $pivotEachMatPct + ($loop->index < $pivotMatExtraOne ? 1 : 0);
                            @endphp
                            <col style="width: {{ $pivotMatColPct }}%;" />
                        @endforeach
                        <col style="width: {{ $pivotColGrandPct }}%;" />
                        <col style="width: {{ $pivotColBosPct }}%;" />
                        <col style="width: {{ $pivotColDoluPct }}%;" />
                        <col style="width: {{ $pivotColKisaPct }}%;" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col" class="bg-[#e7f1ff] dark:bg-zinc-800 px-0.5 py-0.5 text-center text-[0.50rem] font-bold uppercase tracking-wide text-zinc-900 dark:text-zinc-100" title="{{ __('gg.aa.yyyy') }}">{{ __('TARİH') }}</th>
                            @foreach ($materials as $m)
                                @php
                                    $labelRaw = $m['label'];
                                    $firmaSuffix = '';
                                    if (preg_match('/\[(.+)\]\s*$/', $labelRaw, $fMatch)) {
                                        $firmaSuffix = $fMatch[1];
                                        $labelRaw = trim(preg_replace('/\s*\[.+\]\s*$/', '', $labelRaw));
                                    }
                                    $parts = explode(' | ', $labelRaw, 2);
                                    $code = $parts[0] ?? $labelRaw;
                                    $text = $parts[1] ?? '';
                                @endphp
                                <th scope="col" class="mat-col bg-[#e7f1ff] dark:bg-zinc-800 px-0.5 py-0.5 text-center align-bottom text-zinc-900 dark:text-zinc-100" title="{{ $m['label'] }}">
                                    <div class="mat-col-head flex min-w-0 w-full flex-col items-center gap-0.5 text-center">
                                        <span class="mat-col-code text-[0.50rem] uppercase leading-tight">{{ $code }}</span>
                                        @if ($text !== '')
                                            <span class="text-[0.46rem] font-medium leading-snug text-zinc-500 dark:text-zinc-400">{{ $text }}</span>
                                        @endif
                                        @if ($firmaSuffix !== '')
                                            <span class="mat-col-firma text-[0.45rem] leading-snug">{{ $firmaSuffix }}</span>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                            <th scope="col" class="pivot-col-total border-l border-l-[#e0e0e0] bg-[#f0f4f8] dark:bg-zinc-800 px-0.5 py-0.5 text-center text-[0.50rem] font-bold uppercase text-zinc-800 dark:border-l-zinc-600 dark:text-zinc-100">{{ __('TOPLAM') }}</th>
                            <th scope="col" class="bg-[#cfe2ff] text-blue-700 dark:bg-blue-900 dark:text-blue-100 px-0.5 py-0.5 text-center text-[0.48rem] font-bold uppercase leading-tight">
                                <span class="block">{{ __('BOŞ-DOLU TAŞINAN') }}</span>
                                <span class="block font-semibold normal-case">{{ __('GEÇERLİ MİKTAR') }}</span>
                            </th>
                            <th scope="col" class="bg-[#d1e7dd] text-[#0f5132] dark:bg-green-900 dark:text-green-100 px-0.5 py-0.5 text-center text-[0.48rem] font-bold uppercase leading-tight">
                                <span class="block">{{ __('DOLU-DOLU TAŞINAN') }}</span>
                                <span class="block font-semibold normal-case">{{ __('GEÇERLİ MİKTAR') }}</span>
                            </th>
                            <th scope="col" class="bg-[#f8f9fa] text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 px-0.5 py-0.5 text-center text-[0.48rem] font-bold leading-tight">
                                <span class="block uppercase">{{ __('BOŞ-DOLU TAŞINAN') }}</span>
                                <span class="block normal-case font-semibold">{{ __('MALZEME KISA METNİ') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="[&_td]:align-middle [&_th]:align-middle">
                        @foreach ($pivotRowsData as $row)
                            <tr class="group transition-colors">
                                <th scope="row" class="bg-white px-0.5 py-0.5 text-left text-[0.54rem] font-bold tabular-nums text-zinc-900 group-hover:bg-zinc-50 dark:bg-zinc-950 dark:text-zinc-100 dark:group-hover:bg-zinc-900">{{ $row['tarih'] }}</th>
                                @foreach ($materials as $m)
                                    @php
                                        $qty = $row['material_totals'][$m['key']] ?? 0;
                                        $adet = $row['material_counts'][$m['key']] ?? 0;
                                    @endphp
                                    <td class="mat-col bg-white px-0.5 py-0.5 text-center text-[0.52rem] tabular-nums group-hover:bg-zinc-50 dark:bg-zinc-950 dark:group-hover:bg-zinc-900">
                                        <span class="block font-medium leading-tight">
                                            <span class="inline-block whitespace-nowrap">{{ number_format((float) $qty, 2, ',', '.') }} {{ __('Ton') }}</span><span class="tabular-nums"> / </span><span class="inline-block whitespace-nowrap">{{ $adet }} {{ __('Adet') }}</span>
                                        </span>
                                    </td>
                                @endforeach
                                @php
                                    $rowTotalQty = $row['row_total'] ?? 0;
                                    $rowTotalAdet = $row['row_total_count'] ?? 0;
                                @endphp
                                <td class="pivot-col-total min-w-0 border-l border-l-[#e0e0e0] bg-[#f0f4f8] dark:bg-zinc-800 px-0.5 py-0.5 text-center text-[0.52rem] font-semibold tabular-nums text-zinc-800 dark:border-l-zinc-600 dark:text-zinc-100">
                                    <span class="pivot-total-metric block leading-tight tabular-nums">
                                        {{ number_format((float) $rowTotalQty, 2, ',', '.') }} {{ __('Ton') }} / {{ $rowTotalAdet }} {{ __('Adet') }}
                                    </span>
                                </td>
                                <td class="bg-[#cfe2ff] text-blue-700 dark:bg-blue-900 dark:text-blue-100 px-0.5 py-0.5 text-center text-[0.52rem] font-bold tabular-nums">
                                    {{ number_format((float) ($row['boş_dolu'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="bg-[#d1e7dd] text-[#0f5132] dark:bg-green-900 dark:text-green-100 px-0.5 py-0.5 text-center text-[0.52rem] font-bold tabular-nums">
                                    {{ number_format((float) ($row['dolu_dolu'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="min-w-0 whitespace-normal break-words bg-[#f8f9fa] text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 px-0.5 py-0.5 text-center text-[0.52rem] font-medium">
                                    {{ $row['malzeme_kisa_metni'] ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                        @if (! empty($pivotRowsData))
                            <tr class="border-t-2 border-[#d0d0d0] dark:border-zinc-500">
                                <th scope="row" class="bg-[#cfe2ff] text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100 px-0.5 py-0.5 text-left text-[0.52rem] font-bold">{{ __('Toplam') }}</th>
                                @foreach ($materials as $m)
                                    @php
                                        $totQty = $totalsRow['material_totals'][$m['key']] ?? 0;
                                        $totAdet = $totalsRow['material_counts'][$m['key']] ?? 0;
                                    @endphp
                                    <td class="mat-col bg-[#e7f1ff] dark:bg-zinc-800 px-0.5 py-0.5 text-center text-[0.52rem] font-semibold tabular-nums">
                                        <span class="block leading-tight">
                                            <span class="inline-block whitespace-nowrap">{{ number_format((float) $totQty, 2, ',', '.') }} {{ __('Ton') }}</span><span class="tabular-nums"> / </span><span class="inline-block whitespace-nowrap">{{ $totAdet }} {{ __('Adet') }}</span>
                                        </span>
                                    </td>
                                @endforeach
                                @php
                                    $grandTotalQty = $totalsRow['row_total'] ?? 0;
                                    $grandTotalAdet = $totalsRow['row_total_count'] ?? 0;
                                @endphp
                                <td class="pivot-col-total min-w-0 border-l border-l-[#e0e0e0] dark:border-l-zinc-600 bg-[#cfe2ff] text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100 px-0.5 py-0.5 text-center text-[0.52rem] font-bold tabular-nums">
                                    <span class="pivot-total-metric block leading-tight tabular-nums">
                                        {{ number_format((float) $grandTotalQty, 2, ',', '.') }} {{ __('Ton') }} / {{ $grandTotalAdet }} {{ __('Adet') }}
                                    </span>
                                </td>
                                <td class="bg-[#cfe2ff] text-blue-700 dark:bg-blue-900 dark:text-blue-100 px-0.5 py-0.5 text-center text-[0.52rem] font-bold tabular-nums">
                                    {{ number_format((float) ($totalsRow['boş_dolu'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="bg-[#d1e7dd] text-[#0f5132] dark:bg-green-900 dark:text-green-100 px-0.5 py-0.5 text-center text-[0.52rem] font-bold tabular-nums">
                                    {{ number_format((float) ($totalsRow['dolu_dolu'] ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="bg-[#f8f9fa] text-zinc-700 px-0.5 py-0.5 text-center text-[0.52rem] font-bold dark:bg-zinc-800 dark:text-zinc-200">—</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                </div>
            </div>
        @elseif (count($this->pivotRows) === 0)
            @if ($zipUnavailable)
                <div class="p-4 pt-0">
                    <flux:text class="text-zinc-500">{{ __('Pivot tablosu, içe aktarma başarılı olduktan sonra oluşur. Şu an PHP zip kapalı olduğu için .xlsx/.xlsm dosyası okunamadı; önce zip’i açıp sunucuyu yeniden başlatın veya dosyayı .xls olarak yükleyin.') }}</flux:text>
                </div>
            @else
                <div class="p-4 pt-0">
                    <flux:text class="text-zinc-500">{{ __('No pivot data. Load an Excel file or check the report type.') }}</flux:text>
                </div>
            @endif
        @else
            <div class="overflow-x-auto px-1 pb-4 sm:px-0">
                <table class="w-full min-w-full border-collapse border border-zinc-200 text-[0.875rem] dark:border-zinc-700">
                    <thead class="bg-zinc-100 dark:bg-zinc-800">
                        <tr class="text-start">
                            @foreach (array_keys($this->pivotRows[0]) as $col)
                                <th class="whitespace-nowrap border border-zinc-200 px-3 py-2 font-semibold text-zinc-700 dark:border-zinc-600 dark:text-zinc-200">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->pivotRows as $pRow)
                            <tr class="odd:bg-white even:bg-zinc-50/90 dark:odd:bg-zinc-950 dark:even:bg-zinc-900/80">
                                @foreach ($pRow as $cell)
                                    <td class="border border-zinc-200 px-3 py-2 font-mono text-[0.8rem] tabular-nums dark:border-zinc-600">
                                        @if (is_float($cell) || is_int($cell))
                                            {{ number_format((float) $cell, 3, ',', '.') }}
                                        @else
                                            {{ $cell }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>

    @php
        $invoiceRouteGroups = $this->materialPivot['fatura_rota_gruplari'] ?? [];
        $invoiceGrandTotal = (float) ($this->materialPivot['fatura_toplam'] ?? 0);
        $plateInvoiceGroups = $this->materialPivot['fatura_plaka_gruplari'] ?? [];
        $plateInvoiceSummary = $this->materialPivot['fatura_plaka_ozeti'] ?? [];
        $tevkifatliTotal = (float) ($plateInvoiceSummary['tevkifatli_toplam'] ?? 0);
        $tevkifatsizTotal = (float) ($plateInvoiceSummary['diger_toplam'] ?? 0);
        $tevkifatliPlates = $plateInvoiceSummary['tevkifatli_plakalar'] ?? [];
        $plateAmounts = $plateInvoiceSummary['plakaya_gore'] ?? [];
        $tevkifatliPlateSet = [];
        foreach ($tevkifatliPlates as $plateItem) {
            $normalizedPlate = strtoupper(str_replace([' ', '-'], '', trim((string) $plateItem)));
            if ($normalizedPlate !== '') {
                $tevkifatliPlateSet[$normalizedPlate] = true;
            }
        }
        $resolveUnitPrice = function (?string $routeLabel, ?string $tasimaTipi): float {
            $routeNorm = mb_strtoupper(trim((string) $routeLabel));
            $tipNorm = mb_strtoupper(trim((string) $tasimaTipi));

            if (str_contains($routeNorm, 'İSDEMIR') || str_contains($routeNorm, 'ISDEMIR')) {
                return $tipNorm === 'DOLU-DOLU' ? 200.0 : 300.0;
            }
            if (str_contains($routeNorm, 'EKINCILER') || str_contains($routeNorm, 'EKİNCİLER') || str_contains($routeNorm, 'EKINCIELR')) {
                return $tipNorm === 'DOLU-DOLU' ? 250.0 : 350.0;
            }

            return 0.0;
        };
    @endphp

    <flux:card class="p-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('Fatura Kalemleri (Ana Tablo)') }}</flux:heading>
            <flux:badge color="sky" size="sm">
                {{ __('Genel Toplam: :amount Ton', ['amount' => number_format($invoiceGrandTotal, 2, ',', '.')]) }}
            </flux:badge>
        </div>
        @if ($invoiceRouteGroups === [])
            <flux:text class="text-sm text-zinc-500">{{ __('Fatura kalemi verisi bulunamadı.') }}</flux:text>
        @else
            <div class="space-y-4">
                @foreach ($invoiceRouteGroups as $routeGroup)
                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-[900px] w-full border-collapse text-sm">
                            <thead class="bg-zinc-100 dark:bg-zinc-800">
                                <tr>
                                    <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Rota') }}</th>
                                    <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Malzeme Kodu') }}</th>
                                    <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Malzeme') }}</th>
                                    <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Nereden → Nereye') }}</th>
                                    <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Taşıma Tipi') }}</th>
                                    <th class="border border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-600">{{ __('Miktar (Ton)') }}</th>
                                    <th class="border border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-600">{{ __('Birim Fiyat') }}</th>
                                    <th class="border border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-600">{{ __('Toplam Tutar') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (($routeGroup['kalemler'] ?? []) as $item)
                                    @php
                                        $amount = (float) ($item['miktar'] ?? 0);
                                        $unitPrice = $resolveUnitPrice((string) ($routeGroup['route_label'] ?? ''), (string) ($item['tasima_tipi'] ?? ''));
                                        $lineTotal = round($amount * $unitPrice, 2);
                                    @endphp
                                    <tr class="odd:bg-white even:bg-zinc-50 dark:odd:bg-zinc-950 dark:even:bg-zinc-900">
                                        <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $routeGroup['route_label'] ?? '-' }}</td>
                                        <td class="border border-zinc-200 px-3 py-2 font-mono dark:border-zinc-600">{{ $item['material_code'] ?? '-' }}</td>
                                        <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $item['material_short'] ?? '-' }}</td>
                                        <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $item['nerden_nereye'] ?? '-' }}</td>
                                        <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">
                                            @if (($item['tasima_tipi'] ?? '') === 'Dolu-Dolu')
                                                <flux:badge color="green" size="sm">{{ __('Dolu-Dolu') }}</flux:badge>
                                            @else
                                                <flux:badge color="sky" size="sm">{{ __('Boş-Dolu') }}</flux:badge>
                                            @endif
                                        </td>
                                        <td class="border border-zinc-200 px-3 py-2 text-right font-mono tabular-nums dark:border-zinc-600">
                                            {{ number_format($amount, 2, ',', '.') }}
                                        </td>
                                        <td class="border border-zinc-200 px-3 py-2 text-right font-mono tabular-nums dark:border-zinc-600">
                                            {{ number_format($unitPrice, 2, ',', '.') }}
                                        </td>
                                        <td class="border border-zinc-200 px-3 py-2 text-right font-mono tabular-nums dark:border-zinc-600">
                                            {{ number_format($lineTotal, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @php
                                    $routeTotalAmount = (float) ($routeGroup['route_toplam'] ?? 0);
                                    $routeTotalPrice = 0.0;
                                    foreach (($routeGroup['kalemler'] ?? []) as $item) {
                                        $amount = (float) ($item['miktar'] ?? 0);
                                        $unitPrice = $resolveUnitPrice((string) ($routeGroup['route_label'] ?? ''), (string) ($item['tasima_tipi'] ?? ''));
                                        $routeTotalPrice += round($amount * $unitPrice, 2);
                                    }
                                @endphp
                                <tr class="bg-zinc-100 dark:bg-zinc-800">
                                    <td class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600" colspan="5">{{ __('Rota Toplamı') }}</td>
                                    <td class="border border-zinc-200 px-3 py-2 text-right font-semibold tabular-nums dark:border-zinc-600">
                                        {{ number_format($routeTotalAmount, 2, ',', '.') }}
                                    </td>
                                    <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600"></td>
                                    <td class="border border-zinc-200 px-3 py-2 text-right font-semibold tabular-nums dark:border-zinc-600">
                                        {{ number_format($routeTotalPrice, 2, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    <flux:card class="p-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('Plaka Bazlı Fatura Kalemleri') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ __('Tevkifatlı ve tevkifatsız gruplar, ana fatura kalemlerinden plaka dağılımına göre türetilir') }}</flux:text>
        </div>
        @if ($plateInvoiceGroups === [])
            <flux:text class="text-sm text-zinc-500">{{ __('Plaka bazlı fatura kalemi verisi bulunamadı.') }}</flux:text>
        @else
            <div class="space-y-5">
                @foreach ($plateInvoiceGroups as $firmaGroup)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <flux:badge color="zinc" size="sm">{{ $firmaGroup['label'] ?? __('Firma') }}</flux:badge>
                            <flux:text class="text-sm font-semibold tabular-nums">
                                {{ __('Firma Toplamı: :amount Ton', ['amount' => number_format((float) ($firmaGroup['toplam'] ?? 0), 2, ',', '.')]) }}
                            </flux:text>
                        </div>
                        <div class="space-y-3">
                            @foreach (($firmaGroup['tablolar'] ?? []) as $plateTable)
                                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <flux:badge color="amber" size="sm">{{ __('Plaka: :plate', ['plate' => $plateTable['plaka'] ?? '-']) }}</flux:badge>
                                        <flux:text class="text-sm font-semibold tabular-nums">
                                            {{ __('Plaka Toplamı: :amount Ton', ['amount' => number_format((float) ($plateTable['toplam'] ?? 0), 2, ',', '.')]) }}
                                        </flux:text>
                                    </div>
                                    @foreach (($plateTable['rota_gruplari'] ?? []) as $routeGroup)
                                        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 mb-3 last:mb-0">
                                            <table class="min-w-[900px] w-full border-collapse text-sm">
                                                <thead class="bg-zinc-100 dark:bg-zinc-800">
                                                    <tr>
                                                        <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Rota') }}</th>
                                                        <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Malzeme Kodu') }}</th>
                                                        <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Malzeme') }}</th>
                                                        <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Nereden → Nereye') }}</th>
                                                        <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Taşıma Tipi') }}</th>
                                                        <th class="border border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-600">{{ __('Miktar (Ton)') }}</th>
                                                        <th class="border border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-600">{{ __('Birim Fiyat') }}</th>
                                                        <th class="border border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-600">{{ __('Toplam Tutar') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach (($routeGroup['kalemler'] ?? []) as $item)
                                                        @php
                                                            $amount = (float) ($item['miktar'] ?? 0);
                                                            $unitPrice = $resolveUnitPrice((string) ($routeGroup['route_label'] ?? ''), (string) ($item['tasima_tipi'] ?? ''));
                                                            $lineTotal = round($amount * $unitPrice, 2);
                                                        @endphp
                                                        <tr class="odd:bg-white even:bg-zinc-50 dark:odd:bg-zinc-950 dark:even:bg-zinc-900">
                                                            <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $routeGroup['route_label'] ?? '-' }}</td>
                                                            <td class="border border-zinc-200 px-3 py-2 font-mono dark:border-zinc-600">{{ $item['material_code'] ?? '-' }}</td>
                                                            <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $item['material_short'] ?? '-' }}</td>
                                                            <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $item['nerden_nereye'] ?? '-' }}</td>
                                                            <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">
                                                                @if (($item['tasima_tipi'] ?? '') === 'Dolu-Dolu')
                                                                    <flux:badge color="green" size="sm">{{ __('Dolu-Dolu') }}</flux:badge>
                                                                @else
                                                                    <flux:badge color="sky" size="sm">{{ __('Boş-Dolu') }}</flux:badge>
                                                                @endif
                                                            </td>
                                                            <td class="border border-zinc-200 px-3 py-2 text-right font-mono tabular-nums dark:border-zinc-600">
                                                                {{ number_format($amount, 2, ',', '.') }}
                                                            </td>
                                                            <td class="border border-zinc-200 px-3 py-2 text-right font-mono tabular-nums dark:border-zinc-600">
                                                                {{ number_format($unitPrice, 2, ',', '.') }}
                                                            </td>
                                                            <td class="border border-zinc-200 px-3 py-2 text-right font-mono tabular-nums dark:border-zinc-600">
                                                                {{ number_format($lineTotal, 2, ',', '.') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <flux:card class="p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Tevkifatlı Fatura Kalemleri') }}</flux:heading>
                <flux:badge color="green" size="sm">
                    {{ number_format($tevkifatliTotal, 2, ',', '.') }} {{ __('Ton') }}
                </flux:badge>
            </div>
            @if ($tevkifatliPlates === [])
                <flux:text class="text-sm text-zinc-500">{{ __('Tevkifatlı plaka bulunamadı.') }}</flux:text>
            @else
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach ($tevkifatliPlates as $plate)
                        <flux:badge color="green" size="sm">{{ $plate }}</flux:badge>
                    @endforeach
                </div>
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full min-w-[320px] border-collapse text-sm">
                        <thead class="bg-zinc-100 dark:bg-zinc-800">
                            <tr>
                                <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Plaka') }}</th>
                                <th class="border border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-600">{{ __('Miktar (Ton)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plateAmounts as $plateRow)
                                @php
                                    $plate = (string) ($plateRow['plaka'] ?? '');
                                    $normalizedPlate = strtoupper(str_replace([' ', '-'], '', trim($plate)));
                                @endphp
                                @if (isset($tevkifatliPlateSet[$normalizedPlate]))
                                    <tr class="odd:bg-white even:bg-zinc-50 dark:odd:bg-zinc-950 dark:even:bg-zinc-900">
                                        <td class="border border-zinc-200 px-3 py-2 font-mono dark:border-zinc-600">{{ $plate }}</td>
                                        <td class="border border-zinc-200 px-3 py-2 text-right font-mono tabular-nums dark:border-zinc-600">
                                            {{ number_format((float) ($plateRow['miktar'] ?? 0), 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>

        <flux:card class="p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Tevkifatsız Fatura Kalemleri') }}</flux:heading>
                <flux:badge color="sky" size="sm">
                    {{ number_format($tevkifatsizTotal, 2, ',', '.') }} {{ __('Ton') }}
                </flux:badge>
            </div>
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full min-w-[320px] border-collapse text-sm">
                    <thead class="bg-zinc-100 dark:bg-zinc-800">
                        <tr>
                            <th class="border border-zinc-200 px-3 py-2 text-left font-semibold dark:border-zinc-600">{{ __('Plaka') }}</th>
                            <th class="border border-zinc-200 px-3 py-2 text-right font-semibold dark:border-zinc-600">{{ __('Miktar (Ton)') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $hasTevkifatsiz = false; @endphp
                        @foreach ($plateAmounts as $plateRow)
                            @php
                                $plate = (string) ($plateRow['plaka'] ?? '');
                                $normalizedPlate = strtoupper(str_replace([' ', '-'], '', trim($plate)));
                            @endphp
                            @if (! isset($tevkifatliPlateSet[$normalizedPlate]))
                                @php $hasTevkifatsiz = true; @endphp
                                <tr class="odd:bg-white even:bg-zinc-50 dark:odd:bg-zinc-950 dark:even:bg-zinc-900">
                                    <td class="border border-zinc-200 px-3 py-2 font-mono dark:border-zinc-600">{{ $plate }}</td>
                                    <td class="border border-zinc-200 px-3 py-2 text-right font-mono tabular-nums dark:border-zinc-600">
                                        {{ number_format((float) ($plateRow['miktar'] ?? 0), 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        @if (! $hasTevkifatsiz)
                            <tr>
                                <td class="border border-zinc-200 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-600" colspan="2">
                                    {{ __('Tevkifatsız plaka bulunamadı.') }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </flux:card>
    </div>

    {{-- Raw rows (Excel grid: row × column) --}}
    <flux:card id="delivery-import-grid" class="scroll-mt-24 p-4">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('Imported rows') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Her satır Excel’deki bir veri satırına karşılık gelir; sütun sırası aşağıdaki seçime göre değişir. Tüm satırlar için sayfalama kullanın.') }}</flux:text>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                @if (count($this->rowPlateOptions) > 0)
                    <flux:select wire:model.live="rowPlateFilter" :label="__('Plaka filtresi')" class="min-w-[14rem]">
                        <option value="">{{ __('Tüm plakalar') }}</option>
                        @foreach ($this->rowPlateOptions as $plateOpt)
                            <option value="{{ $plateOpt }}">{{ $plateOpt }}</option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:select wire:model.live="rowTableMode" :label="__('Column order')" class="max-w-sm">
                    <option value="excel">{{ __('Excel file (left to right, as in sheet)') }}</option>
                    <option value="schema">{{ __('Report schema (normalized field order)') }}</option>
                </flux:select>
            </div>
        </div>
        @if ($this->rowTableMode === 'excel' && count($this->excelColumnLayout) === 0)
            <flux:callout variant="neutral" class="mb-4" icon="information-circle">
                <flux:callout.text class="text-sm">{{ __('Excel column order is available after a successful upload (column layout is stored with the file). Report schema order is shown below.') }}</flux:callout.text>
            </flux:callout>
        @endif
        @if ($this->rowsPaginator->isEmpty())
            @if ($zipUnavailable)
                <flux:text class="text-zinc-500">{{ __('Satır kaydı yok: sunucu zip eklentisi olmadan modern Excel dosyasını açamadı. Üstteki uyarıyı izleyin veya aynı veriyi .xls olarak kaydedip yeniden içe aktarın; ardından «Yeniden işle» ile deneyin.') }}</flux:text>
            @else
                <flux:text class="text-zinc-500">{{ __('No rows stored for this report.') }}</flux:text>
            @endif
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-[1200px] border-collapse border border-zinc-300 text-xs dark:border-zinc-600 [&_td]:align-middle [&_th]:align-middle">
                    <thead>
                        <tr class="text-start">
                            <th scope="col" class="sticky left-0 z-30 w-24 min-w-[5.5rem] border border-zinc-300 border-e-2 border-e-zinc-400 bg-zinc-200 px-3 py-2 text-left text-[11px] font-semibold leading-tight text-zinc-900 shadow-[4px_0_10px_-2px_rgba(0,0,0,0.08)] dark:border-zinc-600 dark:border-e-zinc-500 dark:bg-zinc-800 dark:text-white dark:shadow-[4px_0_14px_-2px_rgba(0,0,0,0.55)]">
                                <span class="block truncate text-zinc-900 dark:text-white">{{ __('Row No') }}</span>
                            </th>
                            @if ($this->useExcelColumnOrder)
                                @foreach ($this->excelColumnLayout as $col)
                                    @php
                                        $sortKey = $col['expected_index'] !== null ? 'data:'.$col['expected_index'] : null;
                                    @endphp
                                    <th scope="col" class="min-w-[8rem] max-w-[11rem] border border-zinc-300 bg-zinc-200 px-3 py-2 text-left text-[11px] font-semibold leading-tight text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" title="{{ ($col['header'] !== '' ? $col['header'] : '—').' · '.__('Excel column :n', ['n' => $col['excel_col'] + 1]) }}">
                                        <span class="block truncate text-left font-semibold text-zinc-900 dark:text-white" title="{{ $col['header'] !== '' ? $col['header'] : '—' }}">{{ $col['header'] !== '' ? $col['header'] : '—' }}</span>
                                    </th>
                                @endforeach
                            @else
                                @foreach ($this->expectedHeaders as $idx => $label)
                                    @php $sortKey = 'data:'.$idx; @endphp
                                    <th scope="col" class="min-w-[7rem] max-w-[11rem] border border-zinc-300 bg-zinc-200 px-3 py-2 text-left text-[11px] font-semibold leading-tight text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" title="{{ $label }}">
                                        <span class="block truncate text-zinc-900 dark:text-white">{{ $label }}</span>
                                    </th>
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rowsPaginator as $row)
                            @php $display = $this->formatRowForDisplay($row->row_data ?? []); @endphp
                            <tr
                                wire:key="dir-{{ $row->id }}"
                                class="group cursor-pointer odd:bg-white even:bg-zinc-50 transition-colors hover:bg-sky-50 dark:odd:bg-zinc-950 dark:even:bg-zinc-900 dark:hover:bg-zinc-800"
                                wire:click="openRowDetail({{ $row->id }})"
                                role="button"
                                tabindex="0"
                            >
                                <td
                                    @class([
                                        'sticky left-0 z-20 w-24 min-w-[5.5rem] border border-zinc-200 border-e-2 border-e-zinc-300 px-3 py-2 text-left font-mono text-xs tabular-nums text-zinc-700 shadow-[4px_0_10px_-2px_rgba(0,0,0,0.07)] dark:border-zinc-700 dark:border-e-zinc-600 dark:text-zinc-200 dark:shadow-[4px_0_14px_-2px_rgba(0,0,0,0.5)]',
                                        'bg-white dark:bg-zinc-950' => $loop->odd,
                                        'bg-zinc-50 dark:bg-zinc-900' => $loop->even,
                                        'group-hover:bg-sky-50 dark:group-hover:bg-zinc-800' => true,
                                    ])
                                >{{ $row->row_index }}</td>
                                @if ($this->useExcelColumnOrder)
                                    @foreach ($this->excelColumnLayout as $col)
                                        @php
                                            $cell = ($col['expected_index'] !== null) ? ($display[$col['expected_index']] ?? '') : '';
                                            $cellStr = is_scalar($cell) ? (string) $cell : '';
                                        @endphp
                                        <td class="max-w-[11rem] border border-zinc-200 bg-inherit px-3 py-2 font-mono text-xs tabular-nums leading-normal text-zinc-800 whitespace-nowrap dark:border-zinc-700 dark:text-zinc-200" title="{{ e($cellStr) }}">
                                            @if ($col['expected_index'] !== null)
                                                <span class="block truncate">{{ $cell }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                @else
                                    @foreach ($this->expectedHeaders as $idx => $_label)
                                        @php
                                            $cell = $display[$idx] ?? '';
                                            $cellStr = is_scalar($cell) ? (string) $cell : '';
                                        @endphp
                                        <td class="max-w-[11rem] border border-zinc-200 bg-inherit px-3 py-2 font-mono text-xs tabular-nums leading-normal text-zinc-800 whitespace-nowrap dark:border-zinc-700 dark:text-zinc-200" title="{{ e($cellStr) }}"><span class="block truncate">{{ $cell }}</span></td>
                                    @endforeach
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $this->rowsPaginator->links() }}</div>
        @endif
    </flux:card>

    <flux:card class="p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('Plaka düzeltme talepleri') }}</flux:heading>
            @php
                $pendingPlateReqCount = count(array_filter($this->plateCorrectionRequests, fn ($r) => ($r->status ?? '') === 'pending'));
            @endphp
            <flux:badge color="{{ $pendingPlateReqCount > 0 ? 'amber' : 'zinc' }}" size="sm">{{ __('Bekleyen: :n', ['n' => $pendingPlateReqCount]) }}</flux:badge>
        </div>

        @if (! $this->plateCorrectionFeatureEnabled)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text class="text-sm">
                    {{ __('Plaka düzeltme özelliği için veritabanı migration çalıştırılmalı: php artisan migrate') }}
                </flux:callout.text>
            </flux:callout>
        @elseif ($this->plateCorrectionRequests === [])
            <flux:text class="text-sm text-zinc-500">{{ __('Henüz plaka düzeltme talebi yok.') }}</flux:text>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full min-w-[900px] border-collapse text-sm dark:border-zinc-600">
                    <thead class="bg-zinc-100 dark:bg-zinc-800">
                        <tr class="text-start">
                            <th class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600">{{ __('Durum') }}</th>
                            <th class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600">{{ __('Excel satır') }}</th>
                            <th class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600">{{ __('Eski plaka') }}</th>
                            <th class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600">{{ __('Yeni plaka') }}</th>
                            <th class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600">{{ __('Talep eden') }}</th>
                            <th class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600">{{ __('Onaylayan') }}</th>
                            <th class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600">{{ __('Talep nedeni') }}</th>
                            <th class="border border-zinc-200 px-3 py-2 font-semibold dark:border-zinc-600">{{ __('Talep zamanı') }}</th>
                            @can('update', $deliveryImport)
                                <th class="border border-zinc-200 px-3 py-2 text-end font-semibold dark:border-zinc-600">{{ __('İşlem') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->plateCorrectionRequests as $req)
                            <tr class="odd:bg-white even:bg-zinc-50 dark:odd:bg-zinc-950 dark:even:bg-zinc-900">
                                <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">
                                    @if (($req->status ?? '') === 'pending')
                                        <flux:badge color="amber" size="sm">{{ __('Bekliyor') }}</flux:badge>
                                    @elseif (($req->status ?? '') === 'approved')
                                        <flux:badge color="green" size="sm">{{ __('Onaylandı') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ __('Reddedildi') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="border border-zinc-200 px-3 py-2 font-mono text-xs dark:border-zinc-600">{{ $req->row_index }}</td>
                                <td class="border border-zinc-200 px-3 py-2 font-mono dark:border-zinc-600">{{ $req->old_plate }}</td>
                                <td class="border border-zinc-200 px-3 py-2 font-mono dark:border-zinc-600">{{ $req->new_plate }}</td>
                                <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $req->requestedByUser?->name ?? '—' }}</td>
                                <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $req->reviewedByUser?->name ?? '—' }}</td>
                                <td class="border border-zinc-200 px-3 py-2 dark:border-zinc-600">{{ $req->request_reason ?: '—' }}</td>
                                <td class="border border-zinc-200 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-600">{{ optional($req->created_at)->format('d.m.Y H:i') }}</td>
                                @can('update', $deliveryImport)
                                    <td class="border border-zinc-200 px-3 py-2 text-end dark:border-zinc-600">
                                        @if (($req->status ?? '') === 'pending')
                                            <div class="inline-flex items-center gap-2">
                                                <flux:button size="xs" variant="primary" wire:click="approvePlateCorrection({{ $req->id }})">{{ __('Onayla') }}</flux:button>
                                                <flux:button size="xs" variant="ghost" wire:click="rejectPlateCorrection({{ $req->id }})">{{ __('Reddet') }}</flux:button>
                                            </div>
                                        @else
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endif
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>

    {{-- Row detail modal: çerçeve Tailwind arbitrary ile modal içinde güvenilir değil; !important ile zorla --}}
    <flux:modal name="row-detail" class="w-full max-w-4xl">
        <style>
            .row-detail-sheet table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
            .row-detail-sheet th,
            .row-detail-sheet td {
                border: 1px solid #94a3b8 !important;
                padding: 0.5rem 0.75rem !important;
                vertical-align: top;
            }
            .dark .row-detail-sheet th,
            .dark .row-detail-sheet td {
                border-color: #64748b !important;
            }
            .row-detail-sheet th {
                width: 38%;
                font-weight: 600;
                text-align: left;
                background-color: #f4f4f5;
            }
            .dark .row-detail-sheet th {
                background-color: #27272a;
            }
            .row-detail-sheet td {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                background-color: #ffffff;
            }
            .dark .row-detail-sheet td {
                background-color: #18181b;
            }
            .row-detail-sheet td.row-detail-empty {
                font-family: inherit;
                text-align: center;
                padding-top: 1.5rem !important;
                padding-bottom: 1.5rem !important;
            }
            .row-detail-sheet tr:nth-child(even) td { background-color: #fafafa; }
            .row-detail-sheet tr:nth-child(even) th { background-color: #e4e4e7; }
            .dark .row-detail-sheet tr:nth-child(even) td { background-color: #09090b; }
            .dark .row-detail-sheet tr:nth-child(even) th { background-color: #3f3f46; }
        </style>
        <div class="space-y-4 text-zinc-900 dark:text-zinc-100">
            <div class="min-w-0 pe-2">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Satır detayı') }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Excel satır: :n', ['n' => $rowDetailExcelRowIndex ?? '—']) }}
                </p>
                @if ($rowDetailCurrentPlate !== '')
                    <div class="mt-2">
                        <flux:text class="text-sm text-zinc-500">{{ __('Plaka') }}: <span class="font-mono text-zinc-800 dark:text-zinc-200">{{ $rowDetailCurrentPlate }}</span></flux:text>
                    </div>
                    <div class="mt-2">
                        @if ($this->plateCorrectionFeatureEnabled)
                            <flux:button size="sm" variant="outline" icon="magnifying-glass" wire:click="openPlateCorrectionModal">
                                {{ __('Plaka düzeltme talebi oluştur') }}
                            </flux:button>
                        @else
                            <flux:text class="text-xs text-zinc-500">{{ __('Plaka talebi için migration gerekli: php artisan migrate') }}</flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="row-detail-sheet max-h-[70vh] overflow-auto rounded-lg border-2 border-zinc-400 bg-white dark:border-zinc-500 dark:bg-zinc-950">
                <table>
                    <tbody>
                        @forelse ($rowDetailItems as $it)
                            <tr>
                                <th scope="row">{{ $it['label'] }}</th>
                                <td>
                                    <div class="whitespace-pre-wrap break-words">{{ $it['value'] }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="row-detail-empty text-center text-sm text-zinc-600 dark:text-zinc-400" colspan="2">{{ __('Detay bulunamadı.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="plate-correction" class="w-full max-w-lg">
        <div class="space-y-4 text-zinc-900 dark:text-zinc-100">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Plaka düzeltme talebi') }}</h2>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Talep admin onayı sonrası satır verisine ve yüklenen Excel dosyasına uygulanır.') }}</flux:text>
            </div>

            <div class="grid gap-3">
                <flux:input :label="__('Mevcut plaka')" :value="$rowDetailCurrentPlate" readonly />
                <flux:input wire:model="plateCorrectionNewPlate" :label="__('Yeni plaka')" />
                <flux:textarea wire:model="plateCorrectionReason" :label="__('Talep gerekçesi (opsiyonel)')" rows="3" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Vazgeç') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="submitPlateCorrectionRequest">{{ __('Talep gönder') }}</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
