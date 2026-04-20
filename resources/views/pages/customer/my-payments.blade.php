<?php

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Payments')] class extends Component
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

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    private function customerId(): int
    {
        return (int) auth()->user()->customer_id;
    }

    private function tenantId(): int
    {
        return (int) auth()->user()->tenant_id;
    }

    /**
     * @return Builder<Payment>
     */
    private function scopedPaymentsQuery(): Builder
    {
        $cid = $this->customerId();
        $tid = $this->tenantId();

        return Payment::query()
            ->where('tenant_id', $tid)
            ->where(function (Builder $q) use ($cid): void {
                $q->whereHasMorph(
                    'payable',
                    [Invoice::class],
                    fn (Builder $qq) => $qq->where('customer_id', $cid)
                )->orWhereHasMorph(
                    'payable',
                    [Order::class],
                    fn (Builder $qq) => $qq->where('customer_id', $cid)
                );
            })
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    /**
     * @return array{total: int, total_amount: float, pending: int, completed: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $scoped = $this->scopedPaymentsQuery();

        return [
            'total' => (int) (clone $scoped)->count(),
            'total_amount' => (float) (clone $scoped)->where('status', PaymentStatus::Completed)->sum('amount'),
            'pending' => (int) (clone $scoped)->where('status', PaymentStatus::Pending)->count(),
            'completed' => (int) (clone $scoped)->where('status', PaymentStatus::Completed)->count(),
        ];
    }

    #[Computed]
    public function paginatedPayments(): LengthAwarePaginator
    {
        $q = $this->scopedPaymentsQuery()->with('payable');

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('reference_no', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        return $q->paginate(20);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header
        :heading="__('My Payments')"
        :description="__('Recorded payments linked to your invoices or orders (read-only).')"
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:heading size="sm">{{ __('Total records') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->kpiStats['total'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('Completed amount') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ number_format($this->kpiStats['total_amount'], 2) }}</p>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('Pending') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-yellow-600">{{ $this->kpiStats['pending'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('Completed') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->kpiStats['completed'] }}</p>
        </flux:card>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <flux:input wire:model.live.debounce.400ms="filterSearch" :label="__('Search reference / notes')" class="sm:max-w-xs" />
        <flux:select wire:model.live="filterStatus" :label="__('Status')" class="sm:max-w-xs">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (PaymentStatus::cases() as $s)
                <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Method') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Linked to') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedPayments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell>{{ $payment->payment_date?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-medium">
                            {{ number_format((float) $payment->amount, 2) }} {{ $payment->currency_code }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $payment->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $payment->payment_method->label() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $payment->reference_no ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-sm">
                            @php
                                $p = $payment->payable;
                            @endphp
                            @if ($p instanceof Invoice)
                                {{ __('Invoice') }}: {{ $p->invoice_no ?? $p->id }}
                            @elseif ($p instanceof Order)
                                {{ __('Order') }}: {{ $p->order_number ?? $p->id }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">{{ __('No payments found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedPayments->links() }}
        </div>
    </flux:card>
</div>
