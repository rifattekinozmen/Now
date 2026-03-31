<?php

use App\Authorization\LogisticsPermission;
use App\Enums\OrderStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Customer;
use App\Models\CustomerAddress;
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

    // Address book form
    public ?int $editingAddressId = null;

    public string $addrLabel = '';

    public string $addrAddressLine = '';

    public string $addrCity = '';

    public string $addrDistrict = '';

    public string $addrPostalCode = '';

    public string $addrContactName = '';

    public string $addrContactPhone = '';

    public string $addrNotes = '';

    public bool $addrIsDefault = false;

    public bool $showAddressForm = false;

    public function mount(Customer $customer): void
    {
        Gate::authorize('view', $customer);
        $this->customer = $customer->loadCount('orders');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomerAddress>
     */
    #[Computed]
    public function addresses(): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerAddress::query()
            ->where('customer_id', $this->customer->id)
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }

    public function openAddressForm(?int $addressId = null): void
    {
        Gate::authorize('update', $this->customer);

        $this->resetAddressForm();
        $this->showAddressForm = true;

        if ($addressId !== null) {
            $address = CustomerAddress::findOrFail($addressId);
            $this->editingAddressId = $address->id;
            $this->addrLabel        = $address->label;
            $this->addrAddressLine  = $address->address_line ?? '';
            $this->addrCity         = $address->city ?? '';
            $this->addrDistrict     = $address->district ?? '';
            $this->addrPostalCode   = $address->postal_code ?? '';
            $this->addrContactName  = $address->contact_name ?? '';
            $this->addrContactPhone = $address->contact_phone ?? '';
            $this->addrNotes        = $address->notes ?? '';
            $this->addrIsDefault    = (bool) $address->is_default;
        }
    }

    public function saveAddress(): void
    {
        Gate::authorize('update', $this->customer);

        $this->validate([
            'addrLabel'        => 'required|string|max:100',
            'addrAddressLine'  => 'nullable|string|max:255',
            'addrCity'         => 'nullable|string|max:100',
            'addrDistrict'     => 'nullable|string|max:100',
            'addrPostalCode'   => 'nullable|string|max:20',
            'addrContactName'  => 'nullable|string|max:150',
            'addrContactPhone' => 'nullable|string|max:30',
            'addrNotes'        => 'nullable|string|max:1000',
        ]);

        $tenantId = $this->customer->tenant_id;

        if ($this->addrIsDefault) {
            CustomerAddress::query()
                ->where('customer_id', $this->customer->id)
                ->update(['is_default' => false]);
        }

        $data = [
            'tenant_id'     => $tenantId,
            'customer_id'   => $this->customer->id,
            'label'         => $this->addrLabel,
            'address_line'  => $this->addrAddressLine ?: null,
            'city'          => $this->addrCity ?: null,
            'district'      => $this->addrDistrict ?: null,
            'postal_code'   => $this->addrPostalCode ?: null,
            'contact_name'  => $this->addrContactName ?: null,
            'contact_phone' => $this->addrContactPhone ?: null,
            'notes'         => $this->addrNotes ?: null,
            'is_default'    => $this->addrIsDefault,
        ];

        if ($this->editingAddressId !== null) {
            CustomerAddress::findOrFail($this->editingAddressId)->update($data);
        } else {
            CustomerAddress::create($data);
        }

        $this->resetAddressForm();
        unset($this->addresses);
        $this->dispatch('address-saved');
    }

    public function deleteAddress(int $addressId): void
    {
        Gate::authorize('update', $this->customer);

        CustomerAddress::findOrFail($addressId)->delete();
        unset($this->addresses);
    }

    public function setDefaultAddress(int $addressId): void
    {
        Gate::authorize('update', $this->customer);

        CustomerAddress::query()
            ->where('customer_id', $this->customer->id)
            ->update(['is_default' => false]);

        CustomerAddress::findOrFail($addressId)->update(['is_default' => true]);
        unset($this->addresses);
    }

    public function cancelAddressForm(): void
    {
        $this->resetAddressForm();
    }

    private function resetAddressForm(): void
    {
        $this->editingAddressId = null;
        $this->addrLabel        = '';
        $this->addrAddressLine  = '';
        $this->addrCity         = '';
        $this->addrDistrict     = '';
        $this->addrPostalCode   = '';
        $this->addrContactName  = '';
        $this->addrContactPhone = '';
        $this->addrNotes        = '';
        $this->addrIsDefault    = false;
        $this->showAddressForm  = false;
    }

    /**
     * Siparişlerde geçen boşaltma / teslimat metinleri (arşiv).
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
            {{ __('Address book') }}
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
        {{-- Address Book --}}
        <flux:card>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Address book') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Delivery and billing addresses for this customer.') }}
                    </flux:text>
                </div>
                @can(\App\Authorization\LogisticsPermission::CUSTOMERS_WRITE)
                    @if (!$showAddressForm)
                        <flux:button size="sm" variant="primary" wire:click="openAddressForm()">
                            {{ __('Add address') }}
                        </flux:button>
                    @endif
                @endcan
            </div>

            @if ($showAddressForm)
                <div class="mb-6 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <flux:heading size="sm" class="mb-4">
                        {{ $editingAddressId ? __('Edit address') : __('New address') }}
                    </flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Label') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input wire:model="addrLabel" placeholder="{{ __('e.g. Main Warehouse, HQ, Site B') }}" />
                            <flux:error name="addrLabel" />
                        </flux:field>
                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Address line') }}</flux:label>
                            <flux:input wire:model="addrAddressLine" placeholder="{{ __('Street, building no.') }}" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('City') }}</flux:label>
                            <flux:input wire:model="addrCity" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('District') }}</flux:label>
                            <flux:input wire:model="addrDistrict" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Postal code') }}</flux:label>
                            <flux:input wire:model="addrPostalCode" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Contact name') }}</flux:label>
                            <flux:input wire:model="addrContactName" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Contact phone') }}</flux:label>
                            <flux:input wire:model="addrContactPhone" type="tel" />
                        </flux:field>
                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Notes') }}</flux:label>
                            <flux:textarea wire:model="addrNotes" rows="2" />
                        </flux:field>
                        <flux:field class="sm:col-span-2">
                            <flux:checkbox wire:model="addrIsDefault" :label="__('Set as default address')" />
                        </flux:field>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <flux:button size="sm" variant="primary" wire:click="saveAddress">{{ __('Save') }}</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="cancelAddressForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </div>
            @endif

            @if ($this->addresses->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No addresses saved yet.') }}</flux:text>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($this->addresses as $address)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 {{ $address->is_default ? 'border-blue-300 bg-blue-50 dark:border-blue-700 dark:bg-blue-900/20' : '' }}">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $address->label }}</span>
                                    @if ($address->is_default)
                                        <flux:badge color="blue" size="sm">{{ __('Default') }}</flux:badge>
                                    @endif
                                </div>
                                @can(\App\Authorization\LogisticsPermission::CUSTOMERS_WRITE)
                                    <div class="flex shrink-0 gap-1">
                                        @if (!$address->is_default)
                                            <flux:button size="xs" variant="ghost" wire:click="setDefaultAddress({{ $address->id }})" title="{{ __('Set as default') }}">
                                                <flux:icon.star class="h-4 w-4" />
                                            </flux:button>
                                        @endif
                                        <flux:button size="xs" variant="ghost" wire:click="openAddressForm({{ $address->id }})">
                                            <flux:icon.pencil class="h-4 w-4" />
                                        </flux:button>
                                        <flux:button size="xs" variant="ghost" wire:click="deleteAddress({{ $address->id }})"
                                            wire:confirm="{{ __('Delete this address?') }}">
                                            <flux:icon.trash class="h-4 w-4 text-red-500" />
                                        </flux:button>
                                    </div>
                                @endcan
                            </div>
                            <div class="space-y-0.5 text-sm text-zinc-700 dark:text-zinc-300">
                                @if ($address->address_line)
                                    <div>{{ $address->address_line }}</div>
                                @endif
                                @if ($address->district || $address->city)
                                    <div>{{ implode(', ', array_filter([$address->district, $address->city])) }}</div>
                                @endif
                                @if ($address->postal_code)
                                    <div>{{ $address->postal_code }}</div>
                                @endif
                                @if ($address->contact_name || $address->contact_phone)
                                    <div class="mt-2 border-t border-zinc-200 pt-2 text-zinc-500 dark:border-zinc-600">
                                        @if ($address->contact_name)
                                            <span>{{ $address->contact_name }}</span>
                                        @endif
                                        @if ($address->contact_phone)
                                            @if ($address->contact_name) · @endif
                                            <span>{{ $address->contact_phone }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if ($address->notes)
                                    <div class="mt-1 italic text-zinc-500">{{ $address->notes }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($this->recentUnloadingSites->isNotEmpty())
                <details class="mt-6">
                    <summary class="cursor-pointer text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                        {{ __('Sites from past orders (archive)') }}
                    </summary>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                        @foreach ($this->recentUnloadingSites as $site)
                            <li class="break-words">{{ $site }}</li>
                        @endforeach
                    </ul>
                </details>
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
