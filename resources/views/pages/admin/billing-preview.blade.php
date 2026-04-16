<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PricingCondition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Billing Preview')] class extends Component
{
    public string $customerId = '';
    public string $statusFilter = '';
    public string $fromDate = '';
    public string $toDate = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    /** @return Collection<int|string, string> */
    #[Computed]
    public function customers(): Collection
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return collect();
        }

        return Customer::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * @return Collection<int, array{
     *   order_id: int,
     *   order_number: string,
     *   ordered_at: string,
     *   customer_name: string,
     *   status: string,
     *   tonnage: float|null,
     *   loading_site: string|null,
     *   unloading_site: string|null,
     *   currency_code: string,
     *   freight_amount: float|null,
     *   condition_name: string|null,
     *   price_per_ton: float|null,
     *   calculated_amount: float|null,
     *   variance: float|null
     * }>
     */
    #[Computed]
    public function billingRows(): Collection
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return collect();
        }

        $query = Order::query()
            ->with('customer')
            ->whereNotIn('status', [OrderStatus::Draft->value, OrderStatus::Cancelled->value])
            ->orderByDesc('ordered_at');

        if ($this->customerId !== '') {
            $query->where('customer_id', (int) $this->customerId);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->fromDate !== '') {
            $query->whereDate('ordered_at', '>=', $this->fromDate);
        }

        if ($this->toDate !== '') {
            $query->whereDate('ordered_at', '<=', $this->toDate);
        }

        $orders = $query->limit(200)->get();

        $conditions = PricingCondition::query()
            ->where('is_active', true)
            ->get();

        return $orders->map(function (Order $order) use ($conditions): array {
            $orderedDate = Carbon::parse($order->ordered_at)->toDateString();

            $matched = $conditions
                ->filter(fn (PricingCondition $c): bool => $c->customer_id === $order->customer_id
                    && $c->valid_from <= $orderedDate
                    && ($c->valid_until === null || $c->valid_until >= $orderedDate)
                )
                ->first();

            $calculatedAmount = null;
            if ($matched !== null) {
                $pricePerTon = (float) $matched->price_per_ton;
                $basePrice = (float) $matched->base_price;
                if ($pricePerTon > 0 && $order->tonnage !== null) {
                    $calculatedAmount = round($pricePerTon * (float) $order->tonnage, 2);
                } elseif ($basePrice > 0) {
                    $calculatedAmount = $basePrice;
                }
            }

            $variance = null;
            if ($calculatedAmount !== null && $order->freight_amount !== null) {
                $variance = round((float) $order->freight_amount - $calculatedAmount, 2);
            }

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'ordered_at' => Carbon::parse($order->ordered_at)->toDateString(),
                'customer_name' => $order->customer?->name ?? '—',
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'tonnage' => $order->tonnage !== null ? (float) $order->tonnage : null,
                'loading_site' => $order->loading_site,
                'unloading_site' => $order->unloading_site,
                'currency_code' => $order->currency_code,
                'freight_amount' => $order->freight_amount !== null ? (float) $order->freight_amount : null,
                'condition_name' => $matched?->name,
                'price_per_ton' => $matched !== null ? (float) $matched->price_per_ton : null,
                'calculated_amount' => $calculatedAmount,
                'variance' => $variance,
            ];
        });
    }

    /**
     * @return array{total_orders: int, matched_count: int, unmatched_count: int, total_freight: float, total_calculated: float, total_variance: float}
     */
    #[Computed]
    public function summary(): array
    {
        $rows = $this->billingRows;
        $matched = $rows->filter(fn (array $r): bool => $r['condition_name'] !== null);

        return [
            'total_orders' => $rows->count(),
            'matched_count' => $matched->count(),
            'unmatched_count' => $rows->count() - $matched->count(),
            'total_freight' => $rows->sum('freight_amount'),
            'total_calculated' => $matched->sum('calculated_amount'),
            'total_variance' => $matched->whereNotNull('variance')->sum('variance'),
        ];
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return [
            OrderStatus::Confirmed->value => __('Confirmed'),
            OrderStatus::InTransit->value => __('In transit'),
            OrderStatus::Delivered->value => __('Delivered'),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Billing Preview')">
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.reports')" variant="outline" wire:navigate>{{ __('Finance Reports') }}</flux:button>
            <flux:button :href="route('dashboard')" variant="ghost" wire:navigate>{{ __('Back to dashboard') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI summary --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Orders') }}</flux:text>
            <p class="mt-1 text-2xl font-bold">{{ $this->summary['total_orders'] }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Matched') }}</flux:text>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $this->summary['matched_count'] }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Unmatched') }}</flux:text>
            <p class="mt-1 text-2xl font-bold {{ $this->summary['unmatched_count'] > 0 ? 'text-amber-500' : 'text-zinc-700' }}">{{ $this->summary['unmatched_count'] }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Recorded Freight') }}</flux:text>
            <p class="mt-1 text-lg font-bold text-zinc-700">{{ number_format($this->summary['total_freight'], 2, '.', ',') }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Calculated') }}</flux:text>
            <p class="mt-1 text-lg font-bold text-zinc-700">{{ number_format($this->summary['total_calculated'], 2, '.', ',') }}</p>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Variance') }}</flux:text>
            <p class="mt-1 text-lg font-bold {{ $this->summary['total_variance'] != 0 ? 'text-amber-600' : 'text-zinc-700' }}">
                {{ number_format($this->summary['total_variance'], 2, '.', ',') }}
            </p>
        </flux:card>
    </div>

    @if ($this->summary['unmatched_count'] > 0)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Unmatched orders') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __(':count order(s) have no active pricing condition for the customer and date. Review pricing conditions to ensure full coverage.', ['count' => $this->summary['unmatched_count']]) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Filters --}}
    <flux:card class="!p-4">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <flux:select wire:model.live="customerId" :label="__('Customer')">
                    <flux:select.option value="">{{ __('All customers') }}</flux:select.option>
                    @foreach ($this->customers as $id => $name)
                        <flux:select.option :value="$id">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="statusFilter" :label="__('Status')">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach ($this->statusOptions() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:input wire:model.live="fromDate" type="date" :label="__('From')" />
            </div>
            <div>
                <flux:input wire:model.live="toDate" type="date" :label="__('To')" />
            </div>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card class="!p-4">
        <flux:heading size="lg" class="mb-4">{{ __('Billable Orders') }}</flux:heading>

        @if ($this->billingRows->isEmpty())
            <flux:text class="text-sm text-zinc-500">{{ __('No qualifying orders found.') }}</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="pb-2 pr-4">{{ __('Order #') }}</th>
                            <th class="pb-2 pr-4">{{ __('Date') }}</th>
                            <th class="pb-2 pr-4">{{ __('Customer') }}</th>
                            <th class="pb-2 pr-4">{{ __('Status') }}</th>
                            <th class="pb-2 pr-4">{{ __('Route') }}</th>
                            <th class="pb-2 pr-4 text-right">{{ __('Tonnage') }}</th>
                            <th class="pb-2 pr-4">{{ __('Pricing Rule') }}</th>
                            <th class="pb-2 pr-4 text-right">{{ __('Calculated') }}</th>
                            <th class="pb-2 pr-4 text-right">{{ __('Recorded') }}</th>
                            <th class="pb-2 text-right">{{ __('Variance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->billingRows as $row)
                            @php
                                $hasVariance = $row['variance'] !== null && abs($row['variance']) > 0.01;
                                $isUnmatched = $row['condition_name'] === null;
                            @endphp
                            <tr class="@if ($isUnmatched) bg-amber-50 dark:bg-amber-900/10 @elseif ($hasVariance) bg-yellow-50 dark:bg-yellow-900/10 @endif">
                                <td class="py-2 pr-4">
                                    <a href="{{ route('admin.orders.show', $row['order_id']) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                                        {{ $row['order_number'] }}
                                    </a>
                                </td>
                                <td class="py-2 pr-4 text-zinc-500">{{ $row['ordered_at'] }}</td>
                                <td class="py-2 pr-4">{{ $row['customer_name'] }}</td>
                                <td class="py-2 pr-4">
                                    <flux:badge size="sm" variant="outline">{{ $row['status'] }}</flux:badge>
                                </td>
                                <td class="py-2 pr-4 max-w-[160px] truncate text-zinc-500" title="{{ $row['loading_site'] }} → {{ $row['unloading_site'] }}">
                                    {{ $row['loading_site'] ? Str::limit($row['loading_site'], 15) : '—' }}
                                    →
                                    {{ $row['unloading_site'] ? Str::limit($row['unloading_site'], 15) : '—' }}
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ $row['tonnage'] !== null ? number_format($row['tonnage'], 2) : '—' }}
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($row['condition_name'] !== null)
                                        <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $row['condition_name'] }}</span>
                                        @if ($row['price_per_ton'] !== null && $row['price_per_ton'] > 0)
                                            <span class="ml-1 text-xs text-zinc-400">({{ number_format($row['price_per_ton'], 2) }}/t)</span>
                                        @endif
                                    @else
                                        <flux:badge size="sm" variant="warning">{{ __('No rule') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ $row['calculated_amount'] !== null ? number_format($row['calculated_amount'], 2, '.', ',') : '—' }}
                                </td>
                                <td class="py-2 pr-4 text-right">
                                    {{ $row['freight_amount'] !== null ? number_format($row['freight_amount'], 2, '.', ',') : '—' }}
                                </td>
                                <td class="py-2 text-right font-medium @if ($hasVariance) {{ $row['variance'] > 0 ? 'text-emerald-600' : 'text-red-600' }} @else text-zinc-400 @endif">
                                    @if ($row['variance'] !== null)
                                        {{ ($row['variance'] > 0 ? '+' : '') . number_format($row['variance'], 2, '.', ',') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</div>
