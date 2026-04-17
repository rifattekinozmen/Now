<?php

use App\Authorization\LogisticsPermission;
use App\Enums\OrderStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Models\Document;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PricingCondition;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Customer profile')] class extends Component
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

    // Contacts form
    public ?int $editingContactId = null;

    public string $ctName     = '';

    public string $ctPosition = '';

    public string $ctPhone    = '';

    public string $ctEmail    = '';

    public bool $ctIsPrimary  = false;

    public string $ctNotes    = '';

    public bool $showContactForm = false;

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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomerContact>
     */
    #[Computed]
    public function customerContacts(): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerContact::query()
            ->where('customer_id', $this->customer->id)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function openContactForm(?int $id = null): void
    {
        $this->resetContactForm();
        $this->showContactForm = true;

        if ($id !== null) {
            $contact = CustomerContact::query()
                ->where('id', $id)
                ->where('customer_id', $this->customer->id)
                ->firstOrFail();

            $this->editingContactId = $id;
            $this->ctName           = $contact->name;
            $this->ctPosition       = $contact->position ?? '';
            $this->ctPhone          = $contact->phone    ?? '';
            $this->ctEmail          = $contact->email    ?? '';
            $this->ctIsPrimary      = (bool) $contact->is_primary;
            $this->ctNotes          = $contact->notes    ?? '';
        }
    }

    public function saveContact(): void
    {
        Gate::authorize('update', $this->customer);

        $data = $this->validate([
            'ctName'      => ['required', 'string', 'max:150'],
            'ctPosition'  => ['nullable', 'string', 'max:100'],
            'ctPhone'     => ['nullable', 'string', 'max:30'],
            'ctEmail'     => ['nullable', 'email', 'max:150'],
            'ctIsPrimary' => ['boolean'],
            'ctNotes'     => ['nullable', 'string', 'max:500'],
        ]);

        $payload = [
            'name'       => $data['ctName'],
            'position'   => filled($data['ctPosition']) ? $data['ctPosition'] : null,
            'phone'      => filled($data['ctPhone'])    ? $data['ctPhone']    : null,
            'email'      => filled($data['ctEmail'])    ? $data['ctEmail']    : null,
            'is_primary' => $data['ctIsPrimary'],
            'notes'      => filled($data['ctNotes'])    ? $data['ctNotes']    : null,
        ];

        if ($this->editingContactId !== null) {
            CustomerContact::query()
                ->where('id', $this->editingContactId)
                ->where('customer_id', $this->customer->id)
                ->update($payload);
        } else {
            CustomerContact::create([
                'tenant_id'   => $this->customer->tenant_id,
                'customer_id' => $this->customer->id,
                ...$payload,
            ]);
        }

        $this->resetContactForm();
        unset($this->customerContacts);
    }

    public function deleteContact(int $id): void
    {
        Gate::authorize('update', $this->customer);

        CustomerContact::query()
            ->where('id', $id)
            ->where('customer_id', $this->customer->id)
            ->delete();

        unset($this->customerContacts);
    }

    private function resetContactForm(): void
    {
        $this->editingContactId = null;
        $this->ctName           = '';
        $this->ctPosition       = '';
        $this->ctPhone          = '';
        $this->ctEmail          = '';
        $this->ctIsPrimary      = false;
        $this->ctNotes          = '';
        $this->showContactForm  = false;
    }

    public function setTab(string $tab): void
    {
        $allowed = ['orders', 'accounts', 'locations', 'contacts', 'pricing', 'documents', 'payments'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    #[Computed]
    public function customerDocuments(): \Illuminate\Database\Eloquent\Collection
    {
        return Document::query()
            ->where('documentable_type', Customer::class)
            ->where('documentable_id', $this->customer->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array{total: int, completed: int, pending: int, total_amount: float}
     */
    #[Computed]
    public function paymentStats(): array
    {
        $base = Payment::query()
            ->where('payable_type', Customer::class)
            ->where('payable_id', $this->customer->id);

        return [
            'total'        => (int) $base->clone()->count(),
            'completed'    => (int) $base->clone()->where('status', \App\Enums\PaymentStatus::Completed->value)->count(),
            'pending'      => (int) $base->clone()->where('status', \App\Enums\PaymentStatus::Pending->value)->count(),
            'total_amount' => (float) $base->clone()->where('status', \App\Enums\PaymentStatus::Completed->value)->sum('amount'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Payment>
     */
    #[Computed]
    public function customerPayments(): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::query()
            ->where('payable_type', Customer::class)
            ->where('payable_id', $this->customer->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PricingCondition>
     */
    #[Computed]
    public function pricingConditions(): \Illuminate\Database\Eloquent\Collection
    {
        return PricingCondition::query()
            ->where('customer_id', $this->customer->id)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function orderStatusLabel(OrderStatus $status): string
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
        <flux:button type="button" size="sm" :variant="$activeTab === 'documents' ? 'primary' : 'ghost'" wire:click="setTab('documents')">
            {{ __('Documents') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'payments' ? 'primary' : 'ghost'" wire:click="setTab('payments')">
            {{ __('Payments') }}
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
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Company contacts') }}</flux:heading>
                @unless ($showContactForm)
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="openContactForm(null)">
                        {{ __('Add contact') }}
                    </flux:button>
                @endunless
            </div>

            @if ($showContactForm)
                <div class="mb-6 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <flux:heading size="sm" class="mb-4">
                        {{ $editingContactId ? __('Edit contact') : __('New contact') }}
                    </flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input wire:model="ctName" />
                            <flux:error name="ctName" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Position') }}</flux:label>
                            <flux:input wire:model="ctPosition" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Phone') }}</flux:label>
                            <flux:input wire:model="ctPhone" type="tel" />
                        </flux:field>
                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Email') }}</flux:label>
                            <flux:input wire:model="ctEmail" type="email" />
                        </flux:field>
                        <flux:field class="sm:col-span-2">
                            <flux:label>{{ __('Notes') }}</flux:label>
                            <flux:textarea wire:model="ctNotes" rows="2" />
                        </flux:field>
                        <flux:field class="sm:col-span-2">
                            <flux:checkbox wire:model="ctIsPrimary" :label="__('Primary contact')" />
                        </flux:field>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <flux:button size="sm" variant="primary" wire:click="saveContact">{{ __('Save') }}</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="$set('showContactForm', false)">{{ __('Cancel') }}</flux:button>
                    </div>
                </div>
            @endif

            @if ($this->customerContacts->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No contacts for this customer yet.') }}</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-start text-zinc-500 dark:text-zinc-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Name') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Position') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Phone') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Email') }}</th>
                                <th class="py-2 pe-4 font-medium"></th>
                                <th class="py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->customerContacts as $contact)
                                <tr class="text-zinc-700 dark:text-zinc-300">
                                    <td class="py-2 pe-4">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $contact->name }}</span>
                                        @if ($contact->is_primary)
                                            <flux:badge color="blue" size="sm" class="ms-2">{{ __('Primary') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4">{{ $contact->position ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $contact->phone ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $contact->email ?? '—' }}</td>
                                    <td class="py-2 pe-4">
                                        <flux:button size="xs" variant="ghost" icon="pencil" wire:click="openContactForm({{ $contact->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                    </td>
                                    <td class="py-2">
                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteContact({{ $contact->id }})"
                                            wire:confirm="{{ __('Delete this contact?') }}">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @elseif ($activeTab === 'documents')
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('Documents') }}</flux:heading>
            @if ($this->customerDocuments->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No documents for this customer yet.') }}</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-start text-zinc-500 dark:text-zinc-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Title') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Category') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('File type') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Expires at') }}</th>
                                <th class="py-2 font-medium">{{ __('Uploaded') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->customerDocuments as $doc)
                                <tr>
                                    <td class="py-2 pe-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $doc->title }}</td>
                                    <td class="py-2 pe-4">
                                        @if ($doc->category)
                                            <flux:badge color="{{ $doc->category->color() }}" size="sm">{{ $doc->category->label() }}</flux:badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4 text-xs font-mono text-zinc-500">
                                        {{ $doc->file_type?->value ?? '—' }}
                                    </td>
                                    <td class="py-2 pe-4 {{ $doc->expires_at && $doc->expires_at->isPast() ? 'text-red-600 font-semibold' : 'text-zinc-500' }}">
                                        {{ $doc->expires_at?->format('d M Y') ?? '—' }}
                                    </td>
                                    <td class="py-2 text-xs text-zinc-400 whitespace-nowrap">
                                        {{ $doc->created_at->format('d M Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @elseif ($activeTab === 'payments')
        {{-- Payment KPI --}}
        <div class="grid gap-3 sm:grid-cols-4">
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Total payments') }}</flux:text>
                <flux:heading size="lg">{{ $this->paymentStats['total'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Completed') }}</flux:text>
                <flux:heading size="lg" class="text-green-600">{{ $this->paymentStats['completed'] }}</flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Pending') }}</flux:text>
                <flux:heading size="lg" class="{{ $this->paymentStats['pending'] > 0 ? 'text-yellow-500' : '' }}">
                    {{ $this->paymentStats['pending'] }}
                </flux:heading>
            </flux:card>
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Total paid') }}</flux:text>
                <flux:heading size="lg" class="text-green-600">
                    {{ number_format($this->paymentStats['total_amount'], 2) }}
                </flux:heading>
            </flux:card>
        </div>
        <flux:card class="p-4">
            <flux:heading size="sm" class="mb-3">{{ __('Payment history') }}</flux:heading>
            @if ($this->customerPayments->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No payments for this customer yet.') }}</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-start text-zinc-500 dark:text-zinc-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Date') }}</th>
                                <th class="py-2 pe-4 font-medium text-end">{{ __('Amount') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Method') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Status') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Reference no.') }}</th>
                                <th class="py-2 font-medium">{{ __('Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->customerPayments as $payment)
                                <tr>
                                    <td class="py-2 pe-4 whitespace-nowrap">{{ $payment->payment_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="py-2 pe-4 text-end font-mono font-semibold">
                                        {{ number_format((float) $payment->amount, 2) }} {{ $payment->currency_code }}
                                    </td>
                                    <td class="py-2 pe-4">
                                        <flux:badge color="{{ $payment->payment_method->color() }}" size="sm">
                                            {{ $payment->payment_method->label() }}
                                        </flux:badge>
                                    </td>
                                    <td class="py-2 pe-4">
                                        <flux:badge color="{{ $payment->status->color() }}" size="sm">
                                            {{ $payment->status->label() }}
                                        </flux:badge>
                                    </td>
                                    <td class="py-2 pe-4 font-mono text-xs text-zinc-500">{{ $payment->reference_no ?? '—' }}</td>
                                    <td class="py-2 max-w-xs truncate text-zinc-500">{{ $payment->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="mt-3">
                <flux:button :href="route('admin.finance.payments.index')" variant="ghost" size="sm" wire:navigate>
                    {{ __('All payments') }}
                </flux:button>
            </div>
        </flux:card>
    @else
        <flux:card class="p-4">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Pricing / freight agreements') }}</flux:heading>
                <flux:button :href="route('admin.pricing-conditions.index')" size="sm" variant="ghost" wire:navigate>
                    {{ __('Manage all') }}
                </flux:button>
            </div>
            @if ($this->pricingConditions->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('No pricing conditions for this customer yet.') }}
                </flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-start text-zinc-500 dark:text-zinc-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Name') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Route') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Material') }}</th>
                                <th class="py-2 pe-4 font-medium text-end">{{ __('Price/ton') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Valid until') }}</th>
                                <th class="py-2 font-medium">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->pricingConditions as $pc)
                                <tr>
                                    <td class="py-2 pe-4 font-medium">
                                        {{ $pc->name }}
                                        @if ($pc->contract_no)
                                            <div class="text-xs font-mono text-zinc-400">{{ $pc->contract_no }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4 text-zinc-500">{{ $pc->route_from }} → {{ $pc->route_to }}</td>
                                    <td class="py-2 pe-4 font-mono text-xs text-zinc-500">{{ $pc->material_code ?? '—' }}</td>
                                    <td class="py-2 pe-4 text-end font-mono">
                                        {{ number_format((float) $pc->price_per_ton, 2) }}
                                        <span class="text-xs text-zinc-400">{{ $pc->currency_code }}</span>
                                    </td>
                                    <td class="py-2 pe-4">
                                        {{ $pc->valid_until?->format('d.m.Y') ?? __('No expiry') }}
                                    </td>
                                    <td class="py-2">
                                        @if ($pc->is_active)
                                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @endif
</div>
