<?php

use App\Enums\OrderStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
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

    /**
     * Siparişlerde geçen boşaltma / teslimat metinleri (operasyonel adres defteri öncesi özet).
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function recentUnloadingSites(): Collection
    {
        return Order::query()
            ->where('customer_id', $this->customer->id)
            ->whereNotNull('unloading_site')
            ->where('unloading_site', '!=', '')
            ->orderByDesc('ordered_at')
            ->limit(50)
            ->pluck('unloading_site')
            ->unique()
            ->values();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function contactMetaFields(): array
    {
        $meta = $this->customer->meta;
        if (! is_array($meta)) {
            return [];
        }

        $keys = [
            'operation_contact_name' => __('Operations contact'),
            'operation_contact_phone' => __('Operations phone'),
            'accounting_contact_name' => __('Accounting contact'),
            'accounting_email' => __('Accounting email'),
        ];

        $out = [];
        foreach ($keys as $key => $label) {
            if (isset($meta[$key]) && is_string($meta[$key]) && trim($meta[$key]) !== '') {
                $out[$label] = trim($meta[$key]);
            }
        }

        return $out;
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

    /**
     * @return array{total_income:float, total_expense:float, balance:float, pending_count:int}
     */
    #[Computed]
    public function accountSummary(): array
    {
        // Vouchers linked to this customer's orders
        $orderIds = Order::query()->where('customer_id', $this->customer->id)->pluck('id');

        $vouchers = Voucher::query()
            ->whereIn('order_id', $orderIds)
            ->where('status', VoucherStatus::Approved->value)
            ->get();

        $income  = $vouchers->where('type', VoucherType::Income)->sum('amount');
        $expense = $vouchers->where('type', VoucherType::Expense)->sum('amount');

        $pending = Voucher::query()
            ->whereIn('order_id', $orderIds)
            ->where('status', VoucherStatus::Pending->value)
            ->count();

        return [
            'total_income'  => (float) $income,
            'total_expense' => (float) $expense,
            'balance'       => (float) ($income - $expense),
            'pending_count' => $pending,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Voucher>
     */
    #[Computed]
    public function recentVouchers(): \Illuminate\Database\Eloquent\Collection
    {
        $orderIds = Order::query()->where('customer_id', $this->customer->id)->pluck('id');

        return Voucher::query()
            ->with(['cashRegister', 'approvedBy'])
            ->whereIn('order_id', $orderIds)
            ->orderByDesc('voucher_date')
            ->limit(20)
            ->get();
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
    <x-admin.page-header :heading="__('Customer profile')">
        <x-slot name="actions">
            <flux:button :href="route('admin.customers.index')" variant="ghost" wire:navigate>
                {{ __('Back to customers') }}
            </flux:button>
        </x-slot>
        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ $this->customer->legal_name }}
            @if ($this->customer->trade_name)
                — {{ $this->customer->trade_name }}
            @endif
        </flux:text>
        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Tax ID') }}: {{ $this->customer->tax_id ?? '—' }}
            @if ($this->customer->partner_number)
                · {{ __('Partner no.') }}: {{ $this->customer->partner_number }}
            @endif
            · {{ __('Payment term') }}: {{ $this->customer->payment_term_days }} {{ __('days') }}
        </flux:text>
    </x-admin.page-header>

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
        {{-- KPI summary --}}
        <div class="grid gap-3 sm:grid-cols-4">
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Total income') }}</flux:text>
                <flux:heading size="lg" class="text-green-600">{{ number_format($this->accountSummary['total_income'], 2) }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Total expense') }}</flux:text>
                <flux:heading size="lg" class="text-red-500">{{ number_format($this->accountSummary['total_expense'], 2) }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Balance') }}</flux:text>
                <flux:heading size="lg" class="{{ $this->accountSummary['balance'] >= 0 ? 'text-green-600' : 'text-red-500' }}">
                    {{ number_format($this->accountSummary['balance'], 2) }}
                </flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Pending approval') }}</flux:text>
                <flux:heading size="lg" class="{{ $this->accountSummary['pending_count'] > 0 ? 'text-yellow-500' : '' }}">
                    {{ $this->accountSummary['pending_count'] }}
                </flux:heading>
            </flux:card>
        </div>
        <flux:card class="p-4">
            <flux:heading size="sm" class="mb-3">{{ __('Recent vouchers') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Date') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Type') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Amount') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Cash register') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->recentVouchers as $voucher)
                            <tr>
                                <td class="py-2 pe-3 whitespace-nowrap">{{ $voucher->voucher_date?->format('d M Y') }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $voucher->type->color() }}" size="sm">{{ $voucher->type->label() }}</flux:badge>
                                </td>
                                <td class="py-2 pe-3 text-end font-mono">{{ number_format((float)$voucher->amount, 2) }} {{ $voucher->currency_code }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $voucher->status->color() }}" size="sm">{{ $voucher->status->label() }}</flux:badge>
                                </td>
                                <td class="py-2 pe-3 text-zinc-500">{{ $voucher->cashRegister?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-zinc-500">{{ __('No vouchers linked to this customer yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button :href="route('admin.finance.reports')" variant="ghost" size="sm" wire:navigate>
                    {{ __('Finance reports') }}
                </flux:button>
                <flux:button :href="route('admin.finance.payment-due-calendar')" variant="ghost" size="sm" wire:navigate>
                    {{ __('Payment due calendar') }}
                </flux:button>
            </div>
        </flux:card>
    @elseif ($activeTab === 'locations')
        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Delivery locations') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Distinct unloading / delivery sites from orders (last 50 rows). Use this until a dedicated address book is enabled.') }}
            </flux:text>
            @if ($this->recentUnloadingSites->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No unloading site text on orders yet.') }}</flux:text>
            @else
                <ul class="list-inside list-disc space-y-1 text-sm text-zinc-800 dark:text-zinc-200">
                    @foreach ($this->recentUnloadingSites as $site)
                        <li class="break-words">{{ $site }}</li>
                    @endforeach
                </ul>
            @endif
        </flux:card>
    @elseif ($activeTab === 'contacts')
        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Company contacts') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Optional fields stored on customer meta: operation_contact_*, accounting_*') }}
            </flux:text>
            @if (empty($this->contactMetaFields))
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No contact fields in customer meta yet.') }}</flux:text>
            @else
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    @foreach ($this->contactMetaFields as $label => $value)
                        <div class="sm:col-span-2">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
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
