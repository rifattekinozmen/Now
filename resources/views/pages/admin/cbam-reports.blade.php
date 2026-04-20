<?php

use App\Models\CbamReport;
use App\Services\Compliance\CbamReportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('CBAM Carbon Reports')] class extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CbamReport::class);
    }

    /** @return array{total: int, total_co2: float, submitted: int, accepted: int} */
    #[Computed]
    public function kpi(): array
    {
        $base = CbamReport::query();

        return [
            'total'     => (clone $base)->count(),
            'total_co2' => round((float) (clone $base)->sum('co2_kg'), 1),
            'submitted' => (clone $base)->where('status', 'submitted')->count(),
            'accepted'  => (clone $base)->where('status', 'accepted')->count(),
        ];
    }

    /** @return \Illuminate\Contracts\Pagination\LengthAwarePaginator */
    #[Computed]
    public function reports()
    {
        return CbamReport::query()
            ->with('shipment')
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterDateFrom !== '', fn ($q) => $q->where('report_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo !== '', fn ($q) => $q->where('report_date', '<=', $this->filterDateTo))
            ->orderByDesc('report_date')
            ->paginate(20);
    }

    public function exportCsv(CbamReportService $service): \Symfony\Component\HttpFoundation\Response
    {
        Gate::authorize('viewAny', CbamReport::class);

        $all = CbamReport::query()
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterDateFrom !== '', fn ($q) => $q->where('report_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo !== '', fn ($q) => $q->where('report_date', '<=', $this->filterDateTo))
            ->orderByDesc('report_date')
            ->get();

        $csv      = $service->toCsv($all);
        $filename = 'cbam-reports-'.now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'submitted' => 'blue',
            'accepted'  => 'green',
            default     => 'zinc',
        };
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('CBAM Carbon Reports')"
        :description="__('EU Carbon Border Adjustment Mechanism — road freight CO₂ emission reports.')"
    >
        <x-slot name="actions">
            <flux:button wire:click="exportCsv" variant="outline" icon="arrow-down-tray" size="sm">
                {{ __('Export CSV') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPIs --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total reports') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpi['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total CO₂ (kg)') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpi['total_co2'], 1, ',', '.') }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Submitted') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600">{{ $this->kpi['submitted'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Accepted') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpi['accepted'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap gap-4">
            <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
                <option value="">{{ __('All') }}</option>
                <option value="draft">{{ __('Draft') }}</option>
                <option value="submitted">{{ __('Submitted') }}</option>
                <option value="accepted">{{ __('Accepted') }}</option>
            </flux:select>
            <flux:input wire:model.live="filterDateFrom" type="date" :label="__('From')" class="max-w-[160px]" />
            <flux:input wire:model.live="filterDateTo" type="date" :label="__('To')" class="max-w-[160px]" />
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card class="overflow-hidden p-0">
        @if ($this->reports->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-zinc-400">
                {{ __('No CBAM reports yet.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3 font-medium">{{ __('ID') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Shipment') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Distance (km)') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Fuel (L)') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('CO₂ (kg)') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Tonnage') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->reports as $report)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                <td class="px-4 py-3 font-mono text-xs text-zinc-400">#{{ $report->id }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $report->report_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $report->shipment_id ? '#'.$report->shipment_id : '—' }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $report->distance_km ? number_format($report->distance_km, 1) : '—' }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $report->fuel_consumption_l ? number_format($report->fuel_consumption_l, 1) : '—' }}</td>
                                <td class="px-4 py-3 font-semibold text-zinc-800 dark:text-zinc-100">
                                    {{ number_format($report->co2_kg, 1) }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600">{{ $report->tonnage ? number_format($report->tonnage, 1) : '—' }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge color="{{ $this->statusColor($report->status) }}" size="sm">
                                        {{ ucfirst($report->status) }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $this->reports->links() }}
            </div>
        @endif
    </flux:card>

</div>
