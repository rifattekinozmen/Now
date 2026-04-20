<?php

use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Weekly SAS reconciliation')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $filterSasStatus = '';

    public bool $filtersOpen = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
        $this->dateFrom = now()->startOfWeek()->format('Y-m-d');
        $this->dateTo = now()->endOfWeek()->format('Y-m-d');
    }

    public function updatedDateFrom(): void { $this->resetPage(); }

    public function updatedDateTo(): void { $this->resetPage(); }

    public function updatedFilterSasStatus(): void { $this->resetPage(); }

    /**
     * @return array{total: int, matched: int, unmatched: int, total_freight: float}
     */
    #[Computed]
    public function stats(): array
    {
        $q = $this->baseQuery();

        $total = (int) $q->count();
        $matched = (int) (clone $q)->whereNotNull('sas_no')->where('sas_no', '!=', '')->count();
        $unmatched = $total - $matched;

        $totalFreight = (float) (clone $q)->sum('freight_amount');

        return compact('total', 'matched', 'unmatched', 'totalFreight');
    }

    /**
     * @return Builder<Order>
     */
    private function baseQuery(): Builder
    {
        $q = Order::query()->with('customer');

        if ($this->dateFrom !== '') {
            $q->whereDate('ordered_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $q->whereDate('ordered_at', '<=', $this->dateTo);
        }

        if ($this->filterSasStatus === 'matched') {
            $q->whereNotNull('sas_no')->where('sas_no', '!=', '');
        } elseif ($this->filterSasStatus === 'unmatched') {
            $q->where(function (Builder $qq): void {
                $qq->whereNull('sas_no')->orWhere('sas_no', '');
            });
        }

        return $q->orderByDesc('ordered_at');
    }

    #[Computed]
    public function paginatedOrders(): LengthAwarePaginator
    {
        return $this->baseQuery()->paginate(25);
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $orders = $this->baseQuery()->get();

        return response()->streamDownload(function () use ($orders): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Order No', 'SAS No', 'Customer', 'Ordered At', 'Status', 'Freight Amount', 'Currency', 'SAS Match',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->order_number ?? $order->id,
                    $order->sas_no ?? '',
                    $order->customer?->legal_name ?? '',
                    $order->ordered_at?->format('Y-m-d') ?? '',
                    $order->status->value ?? '',
                    $order->freight_amount ?? '',
                    $order->currency_code ?? 'TRY',
                    ($order->sas_no !== null && $order->sas_no !== '') ? 'YES' : 'NO',
                ]);
            }

            fclose($handle);
        }, 'sas-reconciliation-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header
        :heading="__('Weekly SAS reconciliation')"
        :description="__('Match orders to SAS references for the selected period. Export CSV or Logo XML for billing.')"
    >
        <x-slot name="actions">
            <flux:button wire:click="exportCsv" icon="arrow-down-tray" variant="outline">
                {{ __('Export CSV') }}
            </flux:button>
            <flux:button icon="document-arrow-down" variant="outline" :href="route('admin.orders.export.logo.xml')">
                {{ __('Export Logo XML') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- Date filters --}}
    <div class="flex flex-wrap gap-4">
        <flux:input wire:model.live="dateFrom" type="date" :label="__('From')" />
        <flux:input wire:model.live="dateTo" type="date" :label="__('To')" />
        <flux:select wire:model.live="filterSasStatus" :label="__('SAS Match')">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            <flux:select.option value="matched">{{ __('Matched') }}</flux:select.option>
            <flux:select.option value="unmatched">{{ __('Unmatched') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:heading>{{ __('Total Orders') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->stats['total'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('SAS Matched') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->stats['matched'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('SAS Unmatched') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold {{ $this->stats['unmatched'] > 0 ? 'text-red-600' : '' }}">
                {{ $this->stats['unmatched'] }}
            </p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Total Freight') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ number_format($this->stats['totalFreight'], 2) }}</p>
        </flux:card>
    </div>

    {{-- Orders table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Order No') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Ordered At') }}</flux:table.column>
                <flux:table.column>{{ __('SAS No') }}</flux:table.column>
                <flux:table.column>{{ __('Freight') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('SAS Match') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedOrders as $order)
                    @php $hasSas = $order->sas_no !== null && $order->sas_no !== ''; @endphp
                    <flux:table.row :key="$order->id">
                        <flux:table.cell>
                            <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
                               class="text-blue-600 hover:underline text-sm">
                                {{ $order->order_number ?? '#'.$order->id }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $order->customer?->legal_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $order->ordered_at?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">
                            {{ $order->sas_no ?? '' }}
                            @if (! $hasSas)
                                <flux:badge color="red" size="sm">{{ __('Missing') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $order->freight_amount ? number_format((float) $order->freight_amount, 2) : '—' }}
                            {{ $order->currency_code ?? '' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm">{{ $order->status->value ?? '' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($hasSas)
                                <flux:badge color="green" size="sm">✓ {{ __('Matched') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">✗ {{ __('Unmatched') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500">
                            {{ __('No orders found for this period.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedOrders->links() }}
        </div>
    </flux:card>
</div>
