<?php

use App\Authorization\LogisticsPermission;
use App\Enums\OrderStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Document;
use App\Models\Order;
use App\Support\OrderLifecyclePresentation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Order detail')] class extends Component
{
    use RequiresLogisticsAdmin;

    public Order $order;

    public string $activeTab = 'overview';

    public function mount(Order $order): void
    {
        Gate::authorize('view', $order);
        $this->order = $order->load(['customer', 'shipments.vehicle']);
    }

    public function orderStatusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft                 => __('Draft'),
            OrderStatus::PendingPriceApproval  => __('Pending price approval'),
            OrderStatus::Confirmed             => __('Confirmed'),
            OrderStatus::InTransit             => __('In transit'),
            OrderStatus::Delivered             => __('Delivered'),
            OrderStatus::Cancelled             => __('Cancelled'),
        };
    }

    /**
     * Navlun fiyatını onayla (sadece logistics.admin).
     */
    public function approvePrice(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            session()->flash('error', __('Only admins can approve prices.'));

            return;
        }

        Gate::authorize('update', $this->order);

        if ($this->order->isLocked()) {
            session()->flash('error', __('This order is locked and cannot be modified.'));

            return;
        }

        if (! $this->order->status->isPendingPriceApproval()) {
            return;
        }

        $this->order->update([
            'status'             => OrderStatus::Draft,
            'price_approved_by'  => Auth::id(),
            'price_approved_at'  => now(),
        ]);

        $this->order->refresh();
        session()->flash('success', __('Price approved. Order returned to Draft.'));
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

    /**
     * Siparişi kilitle (sadece admin, geri dönüşü olmayan işlem).
     */
    public function lockOrder(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            session()->flash('error', __('Only admins can lock orders.'));

            return;
        }

        Gate::authorize('update', $this->order);

        if ($this->order->isLocked()) {
            return;
        }

        $this->order->update([
            'locked_at' => now(),
            'locked_by' => Auth::id(),
        ]);

        $this->order->refresh();
        session()->flash('success', __('Order locked. No further changes can be made.'));
    }

    public function setOrderTab(string $tab): void
    {
        $allowed = ['overview', 'freight', 'documents'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    #[Computed]
    public function orderDocuments(): \Illuminate\Database\Eloquent\Collection
    {
        return Document::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $this->order->id)
            ->orderByDesc('created_at')
            ->get();
    }
}; ?>

@php
    $o = $this->order;
    $life = $this->lifecycle();
@endphp

<div class="mx-auto flex w-full max-w-7xl flex-col gap-8 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Order detail')">
        <x-slot name="actions">
            @if (! $o->isLocked())
                @can(\App\Authorization\LogisticsPermission::ADMIN)
                    <flux:button size="sm" variant="ghost" icon="lock-closed" wire:click="lockOrder"
                        wire:confirm="{{ __('Lock this order? No further changes will be possible.') }}">
                        {{ __('Lock order') }}
                    </flux:button>
                @endcan
            @endif
            <flux:button :href="route('admin.orders.index')" variant="ghost" wire:navigate>
                {{ __('Back to orders') }}
            </flux:button>
        </x-slot>
        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ $o->order_number }}
            @if ($o->customer)
                — {{ $o->customer->legal_name }}
            @endif
        </flux:text>
    </x-admin.page-header>

    @if ($o->isLocked())
        <flux:callout variant="info" icon="lock-closed">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="sm">{{ __('Order locked') }}</flux:heading>
                    <flux:text class="text-sm">
                        {{ __('This order is locked and cannot be modified.') }}
                        @if ($o->locked_at)
                            {{ __('Locked on') }} {{ $o->locked_at->format('d M Y H:i') }}.
                        @endif
                    </flux:text>
                </div>
            </div>
        </flux:callout>
    @endif

    @if ($life['cancelled'])
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ __('This order was cancelled.') }}
        </flux:callout>
    @endif

    @if ($order->status->isPendingPriceApproval())
        <flux:callout variant="warning" icon="clock">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="sm">{{ __('Price approval required') }}</flux:heading>
                    <flux:text class="text-sm">
                        {{ __('The freight amount is below the minimum threshold set for your company. An admin must approve this order before it can proceed.') }}
                    </flux:text>
                </div>
                @can(\App\Authorization\LogisticsPermission::ADMIN)
                    <flux:button size="sm" variant="primary" wire:click="approvePrice"
                        wire:confirm="{{ __('Approve the freight price and move order back to Draft?') }}">
                        {{ __('Approve price') }}
                    </flux:button>
                @endcan
            </div>
        </flux:callout>
    @endif

    @if (session()->has('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif

    <div class="flex flex-wrap gap-2 border-b border-border pb-2">
        <flux:button
            type="button"
            size="sm"
            :variant="$activeTab === 'overview' ? 'primary' : 'ghost'"
            wire:click="setOrderTab('overview')"
        >
            {{ __('Order overview') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'freight' ? 'primary' : 'ghost'" wire:click="setOrderTab('freight')">
            {{ __('Freight and sites') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'documents' ? 'primary' : 'ghost'" wire:click="setOrderTab('documents')">
            {{ __('Documents') }}
        </flux:button>
    </div>

    @if ($activeTab === 'overview')
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
    @elseif ($activeTab === 'freight')
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
    @elseif ($activeTab === 'documents')
        <flux:card class="p-4">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                <flux:button :href="route('admin.documents.index')" size="sm" variant="ghost" wire:navigate>
                    {{ __('Manage all') }}
                </flux:button>
            </div>
            @if ($this->orderDocuments->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No documents for this order yet.') }}</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-start text-zinc-500 dark:text-zinc-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Title') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Category') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('File type') }}</th>
                                <th class="py-2 font-medium">{{ __('Expires at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->orderDocuments as $doc)
                                @php $expired = $doc->expires_at && $doc->expires_at->isPast(); @endphp
                                <tr class="{{ $expired ? 'bg-red-50 dark:bg-red-950/20' : '' }}">
                                    <td class="py-2 pe-4 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $doc->title }}
                                        @if ($expired)
                                            <flux:badge color="red" size="sm" class="ms-1">{{ __('Expired') }}</flux:badge>
                                        @elseif ($doc->expires_at && $doc->expires_at->diffInDays() <= 30)
                                            <flux:badge color="yellow" size="sm" class="ms-1">{{ __('Expiring soon') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4">
                                        @if ($doc->category)
                                            <flux:badge color="{{ $doc->category->color() }}" size="sm">{{ $doc->category->label() }}</flux:badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4 font-mono text-xs text-zinc-500">{{ $doc->file_type?->value ?? '—' }}</td>
                                    <td class="py-2 {{ $expired ? 'font-semibold text-red-600' : 'text-zinc-500' }}">
                                        {{ $doc->expires_at?->format('d M Y') ?? '—' }}
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
