<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Invoices')] class extends Component
{
    use WithPagination;

    public string $filterSearch = '';

    public string $filterStatus = '';

    public function mount(): void
    {
        if (! auth()->user()?->customer_id) {
            abort(403);
        }
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }

    public function updatedFilterStatus(): void { $this->resetPage(); }

    private function customerId(): int
    {
        return (int) auth()->user()->customer_id;
    }

    /**
     * @return array{total: int, total_amount: float, unpaid_count: int, paid_count: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $base = Invoice::query()->where('customer_id', $this->customerId());

        return [
            'total'        => (int) $base->count(),
            'total_amount' => (float) $base->sum('total'),
            'unpaid_count' => (int) (clone $base)->whereIn('status', ['draft', 'sent', 'overdue'])->count(),
            'paid_count'   => (int) (clone $base)->where('status', 'paid')->count(),
        ];
    }

    #[Computed]
    public function paginatedInvoices(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $q = Invoice::query()
            ->with('order')
            ->where('customer_id', $this->customerId())
            ->orderByDesc('invoice_date');

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where('invoice_no', 'like', $term);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        return $q->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('My Invoices') }}</flux:heading>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:heading>{{ __('Total Invoices') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->kpiStats['total'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Total Amount') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ number_format($this->kpiStats['total_amount'], 2) }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Unpaid') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-yellow-600">{{ $this->kpiStats['unpaid_count'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Paid') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->kpiStats['paid_count'] }}</p>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <flux:input wire:model.live.debounce.400ms="filterSearch" :label="__('Search invoice no')" class="sm:max-w-xs" />
        <flux:select wire:model.live="filterStatus" :label="__('Status')" class="sm:max-w-xs">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (InvoiceStatus::cases() as $s)
                <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Invoice No') }}</flux:table.column>
                <flux:table.column>{{ __('Order') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Due Date') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedInvoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell class="font-mono text-sm font-semibold">
                            {{ $invoice->invoice_no ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($invoice->order)
                                <a href="{{ route('customer.orders.show', $invoice->order) }}" wire:navigate
                                   class="text-sm text-blue-600 hover:underline">
                                    {{ $invoice->order->order_number ?? '#'.$invoice->order->id }}
                                </a>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->invoice_date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($invoice->due_date)
                                <span @class(['text-red-600 font-semibold' => $invoice->due_date->isPast() && $invoice->status !== InvoiceStatus::Paid])>
                                    {{ $invoice->due_date->format('d M Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-semibold">
                            {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$invoice->status->color()" size="sm">
                                {{ $invoice->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            {{ __('No invoices found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedInvoices->links() }}
        </div>
    </flux:card>
</div>
