<?php

use App\Authorization\LogisticsPermission;
use App\Enums\OrderStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Logistics\FreightCalculationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Orders')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithPagination;

    public string $customer_id = '';

    public string $currency_code = 'TRY';

    public string $distance_km = '';

    public string $tonnage = '26';

    public string $freight_amount = '';

    public string $exchange_rate = '';

    public string $incoterms = '';

    public string $loading_site = '';

    public string $unloading_site = '';

    public string $sas_no = '';

    public string $filterSearch = '';

    public string $filterStatus = '';

    public string $sortColumn = 'id';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedPage(): void
    {
        $this->selectedIds = [];
    }

    #[Computed]
    public function statTotal(): int
    {
        return Order::query()->count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return Order::query()->where('status', OrderStatus::Draft)->count();
    }

    #[Computed]
    public function statWithFreight(): int
    {
        return Order::query()->whereNotNull('freight_amount')->count();
    }

    #[Computed]
    public function statCurrencies(): int
    {
        return (int) Order::query()
            ->whereNotNull('currency_code')
            ->selectRaw('count(distinct currency_code) as c')
            ->value('c');
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

    /**
     * @return Builder<Order>
     */
    private function ordersQuery(): Builder
    {
        $q = Order::query()->with('customer');

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('order_number', 'like', $term)
                    ->orWhere('sas_no', 'like', $term)
                    ->orWhereHas('customer', function (Builder $cq) use ($term): void {
                        $cq->where('legal_name', 'like', $term);
                    });
            });
        }

        $allowed = ['id', 'order_number', 'sas_no', 'status', 'currency_code', 'freight_amount', 'ordered_at', 'created_at'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'id';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    public function paginatedOrders(): LengthAwarePaginator
    {
        return $this->ordersQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'order_number', 'sas_no', 'status', 'currency_code', 'freight_amount', 'ordered_at', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
        $this->selectedIds = [];
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedOrders()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedOrders()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $this->selectedIds);
        $allSelected = $pageIds !== [] && count(array_diff($pageIds, $selected)) === 0;

        if ($allSelected) {
            $this->selectedIds = array_values(array_diff($selected, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($selected, $pageIds)));
        }
    }

    public function bulkDeleteSelected(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::ORDERS_WRITE);

        if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $orders = Order::query()->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($orders as $order) {
            Gate::authorize('delete', $order);
            $order->delete();
            $deleted++;
        }

        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('bulk_deleted', $deleted);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Customer>
     */
    public function customerOptions()
    {
        return Customer::query()->orderBy('legal_name')->limit(500)->get();
    }

    public function estimateFreight(FreightCalculationService $freight): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::ORDERS_WRITE);

        Gate::authorize('create', Order::class);

        $this->validate([
            'distance_km' => ['required', 'numeric', 'min:0', 'max:99999'],
            'tonnage' => ['required', 'numeric', 'min:0.1', 'max:999'],
        ]);

        $this->freight_amount = $freight->estimate(
            (float) $this->distance_km,
            (float) $this->tonnage,
        );
    }

    public function saveOrder(): void
    {
        $this->ensureLogisticsAdmin();

        Gate::authorize('create', Order::class);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $validated = $this->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
            ],
            'currency_code' => ['required', 'string', 'size:3', Rule::in(['TRY', 'EUR', 'USD'])],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'tonnage' => ['nullable', 'numeric', 'min:0.1', 'max:999'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'incoterms' => ['nullable', 'string', 'max:12'],
            'loading_site' => ['nullable', 'string', 'max:5000'],
            'unloading_site' => ['nullable', 'string', 'max:5000'],
            'sas_no' => ['nullable', 'string', 'max:64'],
        ]);

        $orderNumber = $this->uniqueOrderNumber();

        Order::query()->create([
            'customer_id' => (int) $validated['customer_id'],
            'order_number' => $orderNumber,
            'status' => OrderStatus::Draft,
            'ordered_at' => now(),
            'currency_code' => strtoupper($validated['currency_code']),
            'freight_amount' => isset($validated['freight_amount']) && $validated['freight_amount'] !== ''
                ? $validated['freight_amount']
                : null,
            'exchange_rate' => isset($validated['exchange_rate']) && $validated['exchange_rate'] !== ''
                ? $validated['exchange_rate']
                : null,
            'distance_km' => isset($validated['distance_km']) && $validated['distance_km'] !== ''
                ? $validated['distance_km']
                : null,
            'tonnage' => isset($validated['tonnage']) && $validated['tonnage'] !== ''
                ? $validated['tonnage']
                : null,
            'incoterms' => $validated['incoterms'] ?: null,
            'loading_site' => $validated['loading_site'] ?: null,
            'unloading_site' => $validated['unloading_site'] ?: null,
            'sas_no' => $validated['sas_no'] ?: null,
        ]);

        $this->reset(
            'customer_id',
            'currency_code',
            'distance_km',
            'tonnage',
            'freight_amount',
            'exchange_rate',
            'incoterms',
            'loading_site',
            'unloading_site',
            'sas_no',
        );
        $this->currency_code = 'TRY';
        $this->tonnage = '26';
    }

    private function uniqueOrderNumber(): string
    {
        do {
            $number = 'ON-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteOrders =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::ORDERS_WRITE);
    @endphp
    <flux:heading size="xl">{{ __('Orders') }}</flux:heading>

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Deleted :count records.', ['count' => session('bulk_deleted')]) }}
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total orders') }}</flux:text>
            <flux:heading size="xl">{{ $this->statTotal }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Draft orders') }}</flux:text>
            <flux:heading size="xl">{{ $this->statDraft }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Freight orders') }}</flux:text>
            <flux:heading size="xl">{{ $this->statWithFreight }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Recorded currencies') }}</flux:text>
            <flux:heading size="xl">{{ $this->statCurrencies }}</flux:heading>
        </flux:card>
    </div>

    @if ($canWriteOrders)
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('New order') }}</flux:heading>
            <form wire:submit="saveOrder" class="flex flex-col gap-4">
                <div>
                    <flux:field :label="__('Customer')">
                        <select
                            wire:model="customer_id"
                            required
                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">{{ __('Select…') }}</option>
                            @foreach ($this->customerOptions() as $c)
                                <option value="{{ $c->id }}">{{ $c->legal_name }}</option>
                            @endforeach
                        </select>
                    </flux:field>
                </div>

                <div>
                    <flux:field :label="__('Currency')">
                        <select
                            wire:model="currency_code"
                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="TRY">TRY</option>
                            <option value="EUR">EUR</option>
                            <option value="USD">USD</option>
                        </select>
                    </flux:field>
                </div>

                <flux:input wire:model="distance_km" type="number" step="0.01" :label="__('Distance (km)')" />
                <flux:input wire:model="tonnage" type="number" step="0.001" :label="__('Tonnage')" />
                <flux:button type="button" wire:click="estimateFreight" variant="ghost">{{ __('Estimate freight') }}</flux:button>
                <flux:input wire:model="freight_amount" :label="__('Freight amount')" />
                <flux:input wire:model="exchange_rate" type="number" step="0.000001" :label="__('Exchange rate (optional)')" />

                <div>
                    <flux:field :label="__('Incoterms')">
                        <select
                            wire:model="incoterms"
                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">{{ __('—') }}</option>
                            <option value="EXW">EXW</option>
                            <option value="FOB">FOB</option>
                            <option value="CIF">CIF</option>
                            <option value="DDP">DDP</option>
                        </select>
                    </flux:field>
                </div>

                <flux:input wire:model="sas_no" :label="__('SAS / PO reference')" />

                <flux:textarea wire:model="loading_site" :label="__('Loading site')" rows="2" />
                <flux:textarea wire:model="unloading_site" :label="__('Unloading site')" rows="2" />

                <flux:button type="submit" variant="primary">{{ __('Save order') }}</flux:button>
            </form>
        </flux:card>
    @endif

    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">{{ __('Advanced filters') }}</flux:heading>
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <flux:card class="!p-4 flex flex-col gap-4">
                <flux:input
                    wire:model.live.debounce.400ms="filterSearch"
                    :label="__('Search (order no, SAS, customer)')"
                />
                <flux:field :label="__('Filter by status')">
                    <select
                        wire:model.live="filterStatus"
                        class="w-full max-w-md rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Enums\OrderStatus::cases() as $case)
                            <option value="{{ $case->value }}">{{ $this->orderStatusLabel($case) }}</option>
                        @endforeach
                    </select>
                </flux:field>
            </flux:card>
        @endif
    </div>

    @if ($canWriteOrders)
        @if (count($selectedIds) > 0)
            <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
                <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="bulkDeleteSelected"
                    wire:confirm="{{ __('Delete selected orders? Related shipments will be removed.') }}"
                >
                    {{ __('Delete selected') }}
                </flux:button>
            </div>
        @endif
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Recent orders') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                @if ($canWriteOrders)
                    <flux:table.column class="w-12">
                        <span class="sr-only">{{ __('Select page') }}</span>
                        <input
                            type="checkbox"
                            class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                            @checked($this->isPageFullySelected())
                            wire:click="toggleSelectPage"
                            wire:key="select-page-orders"
                        />
                    </flux:table.column>
                @endif
                <flux:table.column>
                    <button type="button" wire:click="sortBy('id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('ID') }}
                        @if ($sortColumn === 'id')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('order_number')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Order #') }}
                        @if ($sortColumn === 'order_number')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('sas_no')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('SAS') }}
                        @if ($sortColumn === 'sas_no')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('status')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Status') }}
                        @if ($sortColumn === 'status')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('currency_code')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Currency') }}
                        @if ($sortColumn === 'currency_code')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('freight_amount')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Freight') }}
                        @if ($sortColumn === 'freight_amount')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('ordered_at')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Ordered at') }}
                        @if ($sortColumn === 'ordered_at')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedOrders() as $order)
                    <flux:table.row :key="$order->id">
                        @if ($canWriteOrders)
                            <flux:table.cell>
                                <flux:checkbox wire:model.live="selectedIds" value="{{ $order->id }}" />
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $order->id }}</flux:table.cell>
                        <flux:table.cell>{{ $order->order_number }}</flux:table.cell>
                        <flux:table.cell>{{ $order->sas_no ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $order->customer?->legal_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $this->orderStatusLabel($order->status) }}</flux:table.cell>
                        <flux:table.cell>{{ $order->currency_code }}</flux:table.cell>
                        <flux:table.cell>{{ $order->freight_amount ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $order->ordered_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canWriteOrders ? 9 : 8 }}">{{ __('No orders yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedOrders()->links() }}
        </div>
    </flux:card>
</div>
