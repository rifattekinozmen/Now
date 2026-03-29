<?php

use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Order;
use App\Support\OrderLifecyclePresentation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Order detail')] class extends Component
{
    use RequiresLogisticsAdmin;

    public Order $order;

    public function mount(Order $order): void
    {
        Gate::authorize('view', $order);
        $this->order = $order->load(['customer', 'shipments.vehicle']);
    }

    public function orderStatusLabel(\App\Enums\OrderStatus $status): string
    {
        return match ($status) {
            \App\Enums\OrderStatus::Draft => __('Draft'),
            \App\Enums\OrderStatus::Confirmed => __('Confirmed'),
            \App\Enums\OrderStatus::InTransit => __('In transit'),
            \App\Enums\OrderStatus::Delivered => __('Delivered'),
            \App\Enums\OrderStatus::Cancelled => __('Cancelled'),
        };
    }

    public function shipmentStatusLabel(\App\Enums\ShipmentStatus $status): string
    {
        return match ($status) {
            \App\Enums\ShipmentStatus::Planned => __('Planned'),
            \App\Enums\ShipmentStatus::Dispatched => __('Dispatched'),
            \App\Enums\ShipmentStatus::Delivered => __('Delivered'),
            \App\Enums\ShipmentStatus::Cancelled => __('Cancelled'),
        };
    }

    /**
     * @return array{ cancelled: bool, steps: list<array{key: string, label: string, done: bool, current: bool}> }
     */
    public function lifecycle(): array
    {
        return OrderLifecyclePresentation::forOrder($this->order);
    }
}; ?>

@php
    $o = $this->order;
    $life = $this->lifecycle();
@endphp

<div class="mx-auto flex w-full max-w-5xl flex-col gap-8 p-4 lg:p-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Order detail') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ $o->order_number }}
                @if ($o->customer)
                    — {{ $o->customer->legal_name }}
                @endif
            </flux:text>
        </div>
        <flux:button :href="route('admin.orders.index')" variant="ghost" wire:navigate>
            {{ __('Back to orders') }}
        </flux:button>
    </div>

    @if ($life['cancelled'])
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ __('This order was cancelled.') }}
        </flux:callout>
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-6">{{ __('Order lifecycle') }}</flux:heading>
        <ol class="flex flex-col gap-6 sm:flex-row sm:flex-wrap sm:items-start sm:gap-4">
            @foreach ($life['steps'] as $idx => $step)
                <li class="flex flex-row items-center gap-3 sm:flex-col sm:gap-2 sm:text-center">
                    <span
                        @class([
                            'flex size-10 shrink-0 items-center justify-center rounded-full border-2 text-sm font-semibold',
                            'border-primary bg-primary text-white' => $step['done'],
                            'border-primary bg-white text-primary ring-2 ring-primary dark:bg-zinc-900' => $step['current'] && ! $step['done'],
                            'border-zinc-200 bg-zinc-100 text-zinc-400 dark:border-zinc-600 dark:bg-zinc-800' => ! $step['done'] && ! $step['current'],
                        ])
                    >
                        @if ($step['done'])
                            ✓
                        @else
                            {{ $idx + 1 }}
                        @endif
                    </span>
                    <flux:text @class(['min-w-0 text-sm leading-tight sm:max-w-[9rem]', 'text-zinc-900 dark:text-zinc-100' => $step['done'] || $step['current'], 'text-zinc-500' => ! $step['done'] && ! $step['current']])>
                        {{ $step['label'] }}
                    </flux:text>
                </li>
            @endforeach
        </ol>
        <flux:text class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Payment step follows order status after draft; planning and transit follow shipments.') }}
        </flux:text>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Summary') }}</flux:heading>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->orderStatusLabel($o->status) }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('SAS / PO reference') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $o->sas_no ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Currency') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $o->currency_code }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Freight amount') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $o->freight_amount ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Loading site') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100 whitespace-pre-wrap">{{ $o->loading_site ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Unloading site') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100 whitespace-pre-wrap">{{ $o->unloading_site ?? '—' }}</dd>
            </div>
        </dl>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Shipments') }}</flux:heading>
        @if ($o->shipments->isEmpty())
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No shipments for this order yet.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($o->shipments as $sh)
                        <flux:table.row :key="$sh->id">
                            <flux:table.cell>{{ $sh->id }}</flux:table.cell>
                            <flux:table.cell>{{ $this->shipmentStatusLabel($sh->status) }}</flux:table.cell>
                            <flux:table.cell>{{ $sh->vehicle?->plate ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="sm" variant="ghost" :href="route('admin.shipments.show', $sh)" wire:navigate>
                                    {{ __('Open') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
