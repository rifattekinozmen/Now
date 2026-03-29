<?php

use App\Authorization\LogisticsPermission;
use App\Enums\ShipmentStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Shipment;
use App\Services\Logistics\ShipmentStatusTransitionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shipment detail')] class extends Component
{
    use RequiresLogisticsAdmin;

    public Shipment $shipment;

    public string $pod_note = '';

    public string $pod_received_by = '';

    public function mount(Shipment $shipment): void
    {
        Gate::authorize('view', $shipment);
        $this->shipment = $shipment->load(['order.customer', 'vehicle']);
    }

    public function shipmentStatusLabel(ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::Planned => __('Planned'),
            ShipmentStatus::Dispatched => __('Dispatched'),
            ShipmentStatus::Delivered => __('Delivered'),
            ShipmentStatus::Cancelled => __('Cancelled'),
        };
    }

    public function markDispatched(ShipmentStatusTransitionService $transitions): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::SHIPMENTS_WRITE);

        $s = Shipment::query()->findOrFail($this->shipment->id);
        Gate::authorize('update', $s);

        try {
            $transitions->markDispatched($s);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->shipment = $s->fresh()->load(['order.customer', 'vehicle']);
    }

    public function markDelivered(ShipmentStatusTransitionService $transitions): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::SHIPMENTS_WRITE);

        $s = Shipment::query()->findOrFail($this->shipment->id);
        Gate::authorize('update', $s);

        try {
            $transitions->markDelivered($s, [
                'note' => $this->pod_note,
                'received_by' => $this->pod_received_by,
            ]);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->reset('pod_note', 'pod_received_by');
        $this->shipment = $s->fresh()->load(['order.customer', 'vehicle']);
    }

    public function cancelShipment(ShipmentStatusTransitionService $transitions): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::SHIPMENTS_WRITE);

        $s = Shipment::query()->findOrFail($this->shipment->id);
        Gate::authorize('update', $s);

        try {
            $transitions->cancel($s);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->shipment = $s->fresh()->load(['order.customer', 'vehicle']);
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-8 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteShipments =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::SHIPMENTS_WRITE);
        $s = $this->shipment;
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Shipment') }} #{{ $s->id }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Order') }}: {{ $s->order?->order_number ?? '—' }}
                @if ($s->order?->customer)
                    — {{ $s->order->customer->legal_name }}
                @endif
            </flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($s->order_id)
                <flux:button :href="route('admin.orders.show', $s->order_id)" variant="ghost" wire:navigate>
                    {{ __('Order detail') }}
                </flux:button>
            @endif
            <flux:button :href="route('admin.shipments.index')" variant="ghost" wire:navigate>
                {{ __('Back to shipments') }}
            </flux:button>
        </div>
    </div>

    @if (session()->has('error'))
        <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Public tracking QR') }}</flux:heading>
        <flux:text class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Scan opens a read-only status page (no login).') }}
        </flux:text>
        <div class="flex flex-wrap items-start gap-6">
            <div class="rounded-lg border border-zinc-200 bg-white p-2 dark:border-zinc-600 dark:bg-zinc-900">
                <img
                    src="{{ route('admin.shipments.qr.svg', $s) }}"
                    alt="{{ __('Tracking QR') }}"
                    class="size-40 max-w-full"
                    width="160"
                    height="160"
                />
            </div>
            <div class="min-w-0 flex-1">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tracking link') }}</flux:text>
                <code class="mt-1 block break-all text-sm text-zinc-800 dark:text-zinc-200">{{ route('track.shipment', ['token' => $s->public_reference_token]) }}</code>
            </div>
        </div>
    </flux:card>

    @if ($s->status === \App\Enums\ShipmentStatus::Delivered && is_array($s->pod_payload) && $s->pod_payload !== [])
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Proof of delivery (POD)') }}</flux:heading>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Received by') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $s->pod_payload['received_by'] ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Note') }}</dt>
                    <dd class="whitespace-pre-wrap font-medium text-zinc-900 dark:text-zinc-100">{{ $s->pod_payload['note'] ?? '—' }}</dd>
                </div>
            </dl>
        </flux:card>
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Summary') }}</flux:heading>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->shipmentStatusLabel($s->status) }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Vehicle') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $s->vehicle?->plate ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('SAS / reference') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $s->order?->sas_no ?? '—' }}</dd>
            </div>
        </dl>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-6">{{ __('Lifecycle timeline') }}</flux:heading>

        @if ($s->status === \App\Enums\ShipmentStatus::Cancelled)
            <div class="flex flex-col gap-4 border-s-2 border-red-200 ps-4 dark:border-red-900">
                <div>
                    <flux:badge color="red">{{ __('Cancelled') }}</flux:badge>
                    <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('This shipment was cancelled.') }}
                    </flux:text>
                </div>
            </div>
        @else
            <ol class="relative ms-2 border-s-2 border-zinc-200 ps-6 dark:border-zinc-600">
                <li class="relative mb-8">
                    <span class="absolute -start-[25px] top-1 flex h-3 w-3 rounded-full border-2 border-primary bg-white ring-4 ring-white dark:bg-zinc-900 dark:ring-zinc-900"></span>
                    <flux:heading size="sm">{{ __('Planned') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $s->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    </flux:text>
                </li>
                <li class="relative mb-8">
                    <span
                        @class([
                            'absolute -start-[25px] top-1 flex h-3 w-3 rounded-full border-2 ring-4 ring-white dark:ring-zinc-900',
                            'border-primary bg-primary' => $s->status !== \App\Enums\ShipmentStatus::Planned,
                            'border-zinc-300 bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800' => $s->status === \App\Enums\ShipmentStatus::Planned,
                        ])
                    ></span>
                    <flux:heading size="sm">{{ __('Dispatched') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        @if ($s->dispatched_at)
                            {{ $s->dispatched_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                        @else
                            {{ __('Pending') }}
                        @endif
                    </flux:text>
                </li>
                <li class="relative">
                    <span
                        @class([
                            'absolute -start-[25px] top-1 flex h-3 w-3 rounded-full border-2 ring-4 ring-white dark:ring-zinc-900',
                            'border-primary bg-primary' => $s->status === \App\Enums\ShipmentStatus::Delivered,
                            'border-zinc-300 bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800' => $s->status !== \App\Enums\ShipmentStatus::Delivered,
                        ])
                    ></span>
                    <flux:heading size="sm">{{ __('Delivered') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        @if ($s->delivered_at)
                            {{ $s->delivered_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                        @else
                            {{ __('Pending') }}
                        @endif
                    </flux:text>
                </li>
            </ol>
        @endif
    </flux:card>

    @if ($canWriteShipments && $s->status !== \App\Enums\ShipmentStatus::Delivered && $s->status !== \App\Enums\ShipmentStatus::Cancelled)
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Actions') }}</flux:heading>
            <div class="flex flex-wrap gap-2">
                @if ($s->status === \App\Enums\ShipmentStatus::Planned)
                    <flux:button type="button" variant="primary" wire:click="markDispatched">
                        {{ __('Dispatch') }}
                    </flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelShipment" wire:confirm="{{ __('Cancel this shipment?') }}">
                        {{ __('Cancel') }}
                    </flux:button>
                @elseif ($s->status === \App\Enums\ShipmentStatus::Dispatched)
                    <div class="flex w-full min-w-0 flex-col gap-4 sm:max-w-md">
                        <flux:input wire:model="pod_received_by" :label="__('Received by (optional)')" />
                        <flux:textarea wire:model="pod_note" :label="__('POD note (optional)')" rows="2" />
                        <flux:button type="button" variant="primary" wire:click="markDelivered">
                            {{ __('Mark delivered') }}
                        </flux:button>
                    </div>
                    <flux:button type="button" variant="ghost" wire:click="cancelShipment" wire:confirm="{{ __('Cancel this shipment?') }}">
                        {{ __('Cancel') }}
                    </flux:button>
                @endif
            </div>
        </flux:card>
    @endif
</div>
