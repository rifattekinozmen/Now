<?php

use App\Enums\VoucherType;
use App\Models\Voucher;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Invoices')] class extends Component
{
    use WithPagination;

    public string $filterSearch = '';

    public function mount(): void
    {
        if (! auth()->user()?->customer_id) {
            abort(403);
        }
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }

    private function customerId(): int
    {
        return (int) auth()->user()->customer_id;
    }

    /**
     * @return array{total: int, total_amount: float, unpaid_count: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $base = Voucher::query()
            ->where('type', VoucherType::Income->value)
            ->whereHas('order', fn ($q) => $q->where('customer_id', $this->customerId()));

        return [
            'total'        => (int) $base->count(),
            'total_amount' => (float) $base->sum('amount'),
            'unpaid_count' => (int) (clone $base)->where('status', 'pending')->count(),
        ];
    }

    #[Computed]
    public function paginatedVouchers(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $q = Voucher::query()
            ->with('order')
            ->where('type', VoucherType::Income->value)
            ->whereHas('order', fn ($iq) => $iq->where('customer_id', $this->customerId()))
            ->orderByDesc('voucher_date');

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function ($qq) use ($term): void {
                $qq->where('reference_no', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        return $q->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('My Invoices') }}</flux:heading>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <flux:heading>{{ __('Total Invoices') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->kpiStats['total'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Total Amount') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ number_format($this->kpiStats['total_amount'], 2) }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Pending') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-yellow-600">{{ $this->kpiStats['unpaid_count'] }}</p>
        </flux:card>
    </div>

    {{-- Search --}}
    <flux:input wire:model.live.debounce.400ms="filterSearch" :label="__('Search reference / description')" />

    {{-- Table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Reference No') }}</flux:table.column>
                <flux:table.column>{{ __('Order') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Currency') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedVouchers as $voucher)
                    <flux:table.row :key="$voucher->id">
                        <flux:table.cell class="font-mono text-sm">{{ $voucher->reference_no ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($voucher->order)
                                <a href="{{ route('customer.orders.show', $voucher->order) }}" wire:navigate
                                   class="text-blue-600 hover:underline text-sm">
                                    {{ $voucher->order->order_number ?? '#'.$voucher->order->id }}
                                </a>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $voucher->voucher_date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $voucher->amount, 2) }}</flux:table.cell>
                        <flux:table.cell>{{ $voucher->currency_code }}</flux:table.cell>
                        <flux:table.cell>
                            @php $statusColor = match($voucher->status) {
                                'approved' => 'green',
                                'pending' => 'yellow',
                                'rejected' => 'red',
                                default => 'zinc',
                            }; @endphp
                            <flux:badge :color="$statusColor" size="sm">{{ ucfirst($voucher->status) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate text-sm text-zinc-500">
                            {{ $voucher->description ?? '—' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500">
                            {{ __('No invoices found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedVouchers->links() }}
        </div>
    </flux:card>
</div>
