<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Logistics\FreightCalculationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Orders')] class extends Component
{
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

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Customer>
     */
    public function customerOptions()
    {
        return Customer::query()->orderBy('legal_name')->limit(500)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    public function orderList()
    {
        return Order::query()->with('customer')->orderByDesc('id')->limit(100)->get();
    }

    public function estimateFreight(FreightCalculationService $freight): void
    {
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

<x-layouts::app :title="__('Orders')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
        <flux:heading size="xl">{{ __('Orders') }}</flux:heading>

        <div class="grid gap-6 lg:grid-cols-2">
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
        </div>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Recent orders') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Order #') }}</flux:table.column>
                    <flux:table.column>{{ __('SAS') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Currency') }}</flux:table.column>
                    <flux:table.column>{{ __('Freight') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->orderList() as $order)
                        <flux:table.row :key="$order->id">
                            <flux:table.cell>{{ $order->order_number }}</flux:table.cell>
                            <flux:table.cell>{{ $order->sas_no ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $order->customer?->legal_name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $order->status->value }}</flux:table.cell>
                            <flux:table.cell>{{ $order->currency_code }}</flux:table.cell>
                            <flux:table.cell>{{ $order->freight_amount ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell>{{ __('No orders yet.') }}</flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
