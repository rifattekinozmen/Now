<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Orders')] class extends Component
{
    use WithPagination;

    public string $filterStatus = '';
    public string $search = '';

    public function mount(): void
    {
        if (! auth()->user()?->customer_id) {
            abort(403);
        }
    }

    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function updatedSearch(): void { $this->resetPage(); }

    private function customerId(): int
    {
        return (int) auth()->user()->customer_id;
    }

    /**
     * @return array{total: int, active: int, delivered: int, cancelled: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $cid = $this->customerId();

        return [
            'total'     => Order::query()->where('customer_id', $cid)->count(),
            'active'    => Order::query()->where('customer_id', $cid)
                ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::InTransit->value])
                ->count(),
            'delivered' => Order::query()->where('customer_id', $cid)
                ->where('status', OrderStatus::Delivered->value)
                ->count(),
            'cancelled' => Order::query()->where('customer_id', $cid)
                ->where('status', OrderStatus::Cancelled->value)
                ->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<Order>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Order::query()
            ->where('customer_id', $this->customerId())
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search !== '', fn ($q) => $q->where(function ($q2): void {
                $q2->where('order_number', 'like', '%'.$this->search.'%')
                    ->orWhere('sas_no', 'like', '%'.$this->search.'%');
            }))
            ->orderByDesc('ordered_at')
            ->paginate(15);
    }

    private function statusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft                => 'zinc',
            OrderStatus::PendingPriceApproval => 'yellow',
            OrderStatus::Confirmed            => 'blue',
            OrderStatus::InTransit            => 'amber',
            OrderStatus::Delivered            => 'green',
            OrderStatus::Cancelled            => 'red',
        };
    }

    private function statusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft                => __('Draft'),
            OrderStatus::PendingPriceApproval => __('Pending price approval'),
            OrderStatus::Confirmed            => __('Confirmed'),
            OrderStatus::InTransit            => __('In transit'),
            OrderStatus::Delivered            => __('Delivered'),
            OrderStatus::Cancelled            => __('Cancelled'),
        };
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My Orders') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('All your orders and their current status.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('customer.orders.create')" variant="primary" size="sm" icon="plus" wire:navigate>
                {{ __('New order') }}
            </flux:button>
            <flux:button :href="route('customer.dashboard')" variant="ghost" size="sm" wire:navigate>
                ← {{ __('Dashboard') }}
            </flux:button>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total orders') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600">{{ $this->kpiStats['active'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Delivered') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['delivered'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Cancelled') }}</flux:text>
            <flux:heading size="lg" class="text-red-600">{{ $this->kpiStats['cancelled'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('Search by order number or SAS no...')"
                class="w-full sm:w-64"
                icon="magnifying-glass"
            />
            <flux:select wire:model.live="filterStatus" class="w-full sm:w-48">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Enums\OrderStatus::cases() as $case)
                    <option value="{{ $case->value }}">{{ $this->statusLabel($case) }}</option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card class="overflow-hidden p-0">
        @if ($this->orders->isEmpty())
            <div class="flex flex-col items-center gap-2 py-16 text-center">
                <flux:icon name="clipboard-document-list" class="size-10 text-zinc-300 dark:text-zinc-600" />
                <flux:text class="text-zinc-500">{{ __('No orders found.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3 font-medium">{{ __('Order number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('SAS / PO') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Freight') }}</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->orders as $order)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $order->order_number }}
                                </td>
                                <td class="px-4 py-3 text-zinc-500">{{ $order->sas_no ?? '—' }}</td>
                                <td class="px-4 py-3 text-zinc-500">
                                    {{ $order->ordered_at?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge color="{{ $this->statusColor($order->status) }}" size="sm">
                                        {{ $this->statusLabel($order->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    @if ($order->freight_amount)
                                        {{ number_format($order->freight_amount, 2) }} {{ $order->currency_code }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <flux:link :href="route('customer.orders.show', $order)" wire:navigate class="text-sm">
                                        {{ __('View') }}
                                    </flux:link>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $this->orders->links() }}
            </div>
        @endif
    </flux:card>
</div>
