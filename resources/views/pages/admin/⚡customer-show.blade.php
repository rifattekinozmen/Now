<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Customer profile')] class extends Component
{
    public Customer $customer;

    public string $activeTab = 'orders';

    public function mount(Customer $customer): void
    {
        Gate::authorize('view', $customer);
        $this->customer = $customer->loadCount('orders');
    }

    public function setTab(string $tab): void
    {
        $allowed = ['orders', 'accounts', 'locations', 'contacts', 'pricing'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * @return Builder<Order>
     */
    private function ordersQuery(): Builder
    {
        return Order::query()
            ->where('customer_id', $this->customer->id)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Order> */
    public function getOrdersProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->ordersQuery()->limit(50)->get();
    }

    public function orderStatusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft => __('Draft'),
            OrderStatus::Confirmed => __('Confirmed'),
            OrderStatus::InTransit => __('In transit'),
            OrderStatus::Delivered => __('Delivered'),
            OrderStatus::Cancelled => __('Cancelled'),
        };
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 lg:p-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Customer profile') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ $this->customer->legal_name }}
                @if ($this->customer->trade_name)
                    — {{ $this->customer->trade_name }}
                @endif
            </flux:text>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Tax ID') }}: {{ $this->customer->tax_id ?? '—' }} · {{ __('Payment term') }}:
                {{ $this->customer->payment_term_days }} {{ __('days') }}
            </flux:text>
        </div>
        <flux:button :href="route('admin.customers.index')" variant="ghost" wire:navigate>
            {{ __('Back to customers') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap gap-2 border-b border-border pb-2">
        <flux:button
            type="button"
            size="sm"
            :variant="$activeTab === 'orders' ? 'primary' : 'ghost'"
            wire:click="setTab('orders')"
        >
            {{ __('Order history') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'accounts' ? 'primary' : 'ghost'" wire:click="setTab('accounts')">
            {{ __('Current account') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'locations' ? 'primary' : 'ghost'" wire:click="setTab('locations')">
            {{ __('Delivery locations') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'contacts' ? 'primary' : 'ghost'" wire:click="setTab('contacts')">
            {{ __('Contacts') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'pricing' ? 'primary' : 'ghost'" wire:click="setTab('pricing')">
            {{ __('Pricing / freight') }}
        </flux:button>
    </div>

    @if ($activeTab === 'orders')
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Recent orders') }}</flux:heading>
            @if ($this->orders->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No orders for this customer yet.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Order') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Currency') }}</flux:table.column>
                        <flux:table.column>{{ __('Ordered at') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->orders as $order)
                            <flux:table.row :key="$order->id">
                                <flux:table.cell>
                                    <flux:link :href="route('admin.orders.show', $order)" wire:navigate>
                                        {{ $order->order_number }}
                                    </flux:link>
                                </flux:table.cell>
                                <flux:table.cell>{{ $this->orderStatusLabel($order->status) }}</flux:table.cell>
                                <flux:table.cell>{{ $order->currency_code }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $order->ordered_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @elseif ($activeTab === 'accounts')
        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Current account') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Finance summary and aging reports are under Finance.') }}
            </flux:text>
            @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])
                <flux:button :href="route('admin.finance.reports')" variant="outline" wire:navigate>
                    {{ __('Open finance reports') }}
                </flux:button>
            @endcanany
        </flux:card>
    @elseif ($activeTab === 'locations')
        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Delivery locations') }}</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Favorite addresses / destination book — planned module.') }}
            </flux:text>
        </flux:card>
    @elseif ($activeTab === 'contacts')
        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Company contacts') }}</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Operation and accounting contacts — use customer meta or a later CRM tab.') }}
            </flux:text>
        </flux:card>
    @else
        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Pricing / freight agreements') }}</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('PricingCondition links — planned module.') }}
            </flux:text>
        </flux:card>
    @endif
</div>
