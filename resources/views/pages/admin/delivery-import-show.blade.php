<?php

use App\Models\DeliveryImport;
use App\Services\Delivery\DeliveryReportImportService;
use App\Services\Delivery\DeliveryReportPivotService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Delivery import detail')] class extends Component
{
    use WithPagination;

    public DeliveryImport $deliveryImport;

    /** excel = dosyadaki sol→sağ sütun sırası; schema = rapor şeması (config) sırası */
    public string $rowTableMode = 'excel';

    public string $pivotSortMetric = 'Teslimat miktarı';

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
        return app(DeliveryReportPivotService::class)->buildMaterialPivot($this->deliveryImport);
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
        return $this->deliveryImport->reportRows()
            ->orderBy('row_index')
            ->paginate(25, pageName: 'diRows');
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

    <div class="flex flex-wrap items-center gap-3">
        <flux:button :href="route('admin.delivery-imports.index')" variant="ghost" wire:navigate icon="arrow-left" size="sm">
            {{ __('Delivery Imports') }}
        </flux:button>
        <flux:badge color="{{ $deliveryImport->status->color() }}" size="sm">{{ $deliveryImport->status->label() }}</flux:badge>
    </div>

    <x-admin.page-header
        :heading="$deliveryImport->reference_no ?? __('Import #:id', ['id' => $deliveryImport->id])"
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
                            {{ $this->materialPivotDayCount }} {{ __('Günlük Veri Analiz Raporu') }}
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
                        <flux:button size="sm" variant="outline" icon="arrow-down-tray" class="border-[#e0e0e0] text-slate-800 dark:border-zinc-600 dark:text-zinc-200" :href="route('admin.delivery-imports.material-pivot.csv', $deliveryImport)">
                            {{ __('Pivot CSV') }}
                        </flux:button>
                        <flux:button size="sm" variant="outline" icon="arrow-down-tray" class="border-[#e0e0e0] text-slate-800 dark:border-zinc-600 dark:text-zinc-200" :href="route('admin.delivery-imports.invoice-lines.csv', $deliveryImport)">
                            {{ __('Fatura Kalemleri CSV') }}
                        </flux:button>
                        <flux:button size="sm" variant="outline" icon="arrow-left" class="border-[#e0e0e0] text-slate-800 dark:border-zinc-600 dark:text-zinc-200" href="#delivery-import-grid" wire:navigate="false">
                            {{ __('Rapor Detayı') }}
                        </flux:button>
                        <flux:button size="sm" variant="outline" icon="bars-3" class="border-[#e0e0e0] text-slate-800 dark:border-zinc-600 dark:text-zinc-200" :href="route('admin.delivery-imports.index')" wire:navigate>
                            {{ __('Listeye Dön') }}
                        </flux:button>
                    </div>
                </div>
                <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Tarih satırları ve malzeme sütunları; hücrede Geçerli Miktar toplamı (Ton / Adet). Sağdaki renkli sütunlar BOŞ-DOLU ve DOLU-DOLU taşınan geçerli miktarlardır.') }}</flux:text>
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
                    .dark .veri-analiz-pivot tbody tr:not(:last-child) td.mat-col { color: #cbd5e1 !important; }
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
                    <flux:text class="text-zinc-500">{{ __('No pivot data. Import an Excel file or check report type.') }}</flux:text>
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

    {{-- Raw rows (Excel grid: row × column) --}}
    <flux:card id="delivery-import-grid" class="scroll-mt-24 p-4">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('Imported rows') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Her satır Excel’deki bir veri satırına karşılık gelir; sütun sırası aşağıdaki seçime göre değişir. Tüm satırlar için sayfalama kullanın.') }}</flux:text>
            </div>
            <flux:select wire:model.live="rowTableMode" :label="__('Column order')" class="max-w-sm">
                <option value="excel">{{ __('Excel file (left to right, as in sheet)') }}</option>
                <option value="schema">{{ __('Report schema (normalized field order)') }}</option>
            </flux:select>
        </div>
        @if ($this->rowTableMode === 'excel' && count($this->excelColumnLayout) === 0)
            <flux:callout variant="neutral" class="mb-4" icon="information-circle">
                <flux:callout.text class="text-sm">{{ __('Excel column order is available after a successful import (layout is saved with the file). Showing report schema order below.') }}</flux:callout.text>
            </flux:callout>
        @endif
        @if ($this->rowsPaginator->isEmpty())
            @if ($zipUnavailable)
                <flux:text class="text-zinc-500">{{ __('Satır kaydı yok: sunucu zip eklentisi olmadan modern Excel dosyasını açamadı. Üstteki uyarıyı izleyin veya aynı veriyi .xls olarak kaydedip yeniden içe aktarın; ardından «Yeniden işle» ile deneyin.') }}</flux:text>
            @else
                <flux:text class="text-zinc-500">{{ __('No rows stored for this import.') }}</flux:text>
            @endif
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-[1200px] divide-y divide-zinc-200 text-xs dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr class="text-start text-zinc-600 dark:text-zinc-300">
                            <th class="sticky left-0 z-10 bg-zinc-50 py-2 pe-2 ps-2 font-medium dark:bg-zinc-900/80">{{ __('Excel satır no') }}</th>
                            @if ($this->useExcelColumnOrder)
                                @foreach ($this->excelColumnLayout as $col)
                                    <th class="min-w-[7rem] max-w-[14rem] py-2 pe-2 font-medium" title="{{ __('Excel column :n', ['n' => $col['excel_col'] + 1]) }}">
                                        <span class="line-clamp-3 text-left">{{ $col['header'] !== '' ? $col['header'] : '—' }}</span>
                                    </th>
                                @endforeach
                            @else
                                @foreach ($this->expectedHeaders as $label)
                                    <th class="min-w-[8rem] py-2 pe-2 font-medium">{{ $label }}</th>
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rowsPaginator as $row)
                            @php $display = $this->formatRowForDisplay($row->row_data ?? []); @endphp
                            <tr wire:key="dir-{{ $row->id }}" class="align-top">
                                <td class="sticky left-0 z-10 bg-white py-2 pe-2 ps-2 font-mono text-zinc-500 dark:bg-zinc-900">{{ $row->row_index }}</td>
                                @if ($this->useExcelColumnOrder)
                                    @foreach ($this->excelColumnLayout as $col)
                                        @php
                                            $cell = ($col['expected_index'] !== null) ? ($display[$col['expected_index']] ?? '') : '';
                                        @endphp
                                        <td class="max-w-[16rem] whitespace-pre-wrap break-words py-2 pe-2 font-mono text-[11px] leading-snug text-zinc-800 dark:text-zinc-200" title="{{ e(is_scalar($cell) ? (string) $cell : '') }}">
                                            @if ($col['expected_index'] !== null)
                                                {{ $cell }}
                                            @endif
                                        </td>
                                    @endforeach
                                @else
                                    @foreach ($this->expectedHeaders as $idx => $_label)
                                        @php $cell = $display[$idx] ?? ''; @endphp
                                        <td class="max-w-[16rem] whitespace-pre-wrap break-words py-2 pe-2 font-mono text-[11px] leading-snug text-zinc-800 dark:text-zinc-200" title="{{ e(is_scalar($cell) ? (string) $cell : '') }}">{{ $cell }}</td>
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

</div>
