<?php

use App\Authorization\LogisticsPermission;
use App\Enums\ShipmentStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Shipment;
use App\Services\Logistics\PodDeliveryPhotoStorage;
use App\Services\Logistics\ShipmentStatusTransitionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

new #[Lazy, Title('Shipment detail')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;

    public Shipment $shipment;

    public string $activeTab = 'overview';

    public string $pod_note = '';

    public string $pod_received_by = '';

    /** @var string data:image/png;base64,... from canvas (optional) */
    public string $pod_signature_data = '';

    public string $pod_latitude = '';

    public string $pod_longitude = '';

    /** @var mixed */
    public $pod_photo = null;

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

    public function markDelivered(ShipmentStatusTransitionService $transitions, PodDeliveryPhotoStorage $podPhotos): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::SHIPMENTS_WRITE);

        $strict = (bool) config('logistics.ipod.strict', false);

        $rules = [
            'pod_note' => ['nullable', 'string', 'max:2000'],
            'pod_received_by' => ['nullable', 'string', 'max:255'],
            'pod_signature_data' => ['nullable', 'string', 'max:786432'],
            'pod_latitude' => ['nullable', 'string', 'max:32'],
            'pod_longitude' => ['nullable', 'string', 'max:32'],
            'pod_photo' => ['nullable', 'file', 'image', 'max:2048'],
        ];

        if ($strict) {
            $rules['pod_signature_data'] = ['required', 'string', 'max:786432'];
            $rules['pod_latitude'] = ['required', 'numeric'];
            $rules['pod_longitude'] = ['required', 'numeric'];
            $rules['pod_photo'] = ['required', 'file', 'image', 'max:2048'];
        }

        $this->validate($rules);

        $s = Shipment::query()->findOrFail($this->shipment->id);
        Gate::authorize('update', $s);

        $sig = trim($this->pod_signature_data);

        $pod = [
            'note' => $this->pod_note,
            'received_by' => $this->pod_received_by,
            'signature_data_url' => $sig !== '' ? $sig : null,
        ];

        $lat = trim($this->pod_latitude);
        $lng = trim($this->pod_longitude);
        if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
            $pod['latitude'] = (float) $lat;
            $pod['longitude'] = (float) $lng;
        }

        if ($this->pod_photo !== null) {
            $pod['photo_storage_path'] = $podPhotos->storeFromUpload($s, $this->pod_photo);
        }

        try {
            $transitions->markDelivered($s, $pod);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->reset('pod_note', 'pod_received_by', 'pod_signature_data', 'pod_latitude', 'pod_longitude', 'pod_photo');
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

    public function setShipmentTab(string $tab): void
    {
        $allowed = ['overview', 'tracking', 'timeline', 'operations'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-8 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteShipments =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::SHIPMENTS_WRITE);
        $s = $this->shipment;
    @endphp

    <x-admin.page-header :heading="__('Shipment').' #'.$s->id">
        <x-slot name="actions">
            @if ($s->order_id)
                <flux:button :href="route('admin.orders.show', $s->order_id)" variant="ghost" wire:navigate>
                    {{ __('Order detail') }}
                </flux:button>
            @endif
            <flux:button :href="route('admin.shipments.index')" variant="ghost" wire:navigate>
                {{ __('Back to shipments') }}
            </flux:button>
        </x-slot>
        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Order') }}: {{ $s->order?->order_number ?? '—' }}
            @if ($s->order?->customer)
                — {{ $s->order->customer->legal_name }}
            @endif
        </flux:text>
    </x-admin.page-header>

    @if (session()->has('error'))
        <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
    @endif

    <div class="flex flex-wrap gap-2 border-b border-border pb-2">
        <flux:button
            type="button"
            size="sm"
            :variant="$activeTab === 'overview' ? 'primary' : 'ghost'"
            wire:click="setShipmentTab('overview')"
        >
            {{ __('Shipment overview') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'tracking' ? 'primary' : 'ghost'" wire:click="setShipmentTab('tracking')">
            {{ __('Tracking and QR') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'timeline' ? 'primary' : 'ghost'" wire:click="setShipmentTab('timeline')">
            {{ __('Timeline') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'operations' ? 'primary' : 'ghost'" wire:click="setShipmentTab('operations')">
            {{ __('Operations') }}
        </flux:button>
    </div>

    @if ($activeTab === 'tracking')
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
    @elseif ($activeTab === 'overview')
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
    @elseif ($activeTab === 'timeline')
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
    @elseif ($activeTab === 'operations')
        @if ($s->status === \App\Enums\ShipmentStatus::Delivered && is_array($s->pod_payload) && $s->pod_payload !== [])
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Proof of delivery (POD)') }}</flux:heading>
            <div class="mb-4 flex flex-wrap gap-2">
                <flux:button :href="route('admin.shipments.pod.print', $s)" variant="outline" target="_blank">
                    {{ __('Print POD') }}
                </flux:button>
                @if (! empty($s->pod_payload['signature_storage_path']))
                    <flux:button :href="route('admin.shipments.pod.signature', $s)" variant="ghost" download>
                        {{ __('Download signature (PNG)') }}
                    </flux:button>
                @endif
                @if (! empty($s->pod_payload['photo_storage_path']))
                    <flux:button :href="route('admin.shipments.pod.delivery-photo', $s)" variant="ghost" target="_blank">
                        {{ __('Open delivery photo') }}
                    </flux:button>
                @endif
            </div>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Received by') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $s->pod_payload['received_by'] ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Note') }}</dt>
                    <dd class="whitespace-pre-wrap font-medium text-zinc-900 dark:text-zinc-100">{{ $s->pod_payload['note'] ?? '—' }}</dd>
                </div>
                @if (! empty($s->pod_payload['signed_at']))
                    <div class="sm:col-span-2">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Signed at') }}</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $s->pod_payload['signed_at'] }}</dd>
                    </div>
                @endif
            </dl>
            @if (! empty($s->pod_payload['signature_storage_path']))
                <div class="mt-4">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Signature') }}</flux:text>
                    <img
                        src="{{ route('admin.shipments.pod.signature', $s) }}"
                        alt="{{ __('Signature') }}"
                        class="mt-2 max-h-48 max-w-full rounded border border-zinc-200 dark:border-zinc-600"
                    />
                </div>
            @endif
            @if (! empty($s->pod_payload['photo_storage_path']))
                <div class="mt-4">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Delivery photo') }}</flux:text>
                    <img
                        src="{{ route('admin.shipments.pod.delivery-photo', $s) }}"
                        alt="{{ __('Delivery photo') }}"
                        class="mt-2 max-h-64 max-w-full rounded border border-zinc-200 dark:border-zinc-600"
                    />
                </div>
            @endif
        </flux:card>
        @endif

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
                    <div
                        class="flex w-full min-w-0 flex-col gap-4 sm:max-w-md"
                        x-data="{
                            drawing: false,
                            dirty: false,
                            bindCanvas() {
                                const c = this.$refs.pad
                                if (!c) return
                                const ctx = c.getContext('2d')
                                ctx.lineWidth = 2
                                ctx.strokeStyle = document.documentElement.classList.contains('dark') ? '#f4f4f5' : '#18181b'
                                ctx.lineCap = 'round'
                                const pos = (ev) => {
                                    const r = c.getBoundingClientRect()
                                    return { x: ev.clientX - r.left, y: ev.clientY - r.top }
                                }
                                c.addEventListener('pointerdown', (e) => {
                                    e.preventDefault()
                                    this.drawing = true
                                    this.dirty = true
                                    try {
                                        c.setPointerCapture(e.pointerId)
                                    } catch (_) {}
                                    const p = pos(e)
                                    ctx.beginPath()
                                    ctx.moveTo(p.x, p.y)
                                })
                                c.addEventListener('pointermove', (e) => {
                                    if (!this.drawing) return
                                    const p = pos(e)
                                    ctx.lineTo(p.x, p.y)
                                    ctx.stroke()
                                })
                                const end = () => {
                                    this.drawing = false
                                }
                                c.addEventListener('pointerup', end)
                                c.addEventListener('pointercancel', end)
                            },
                            clearPad() {
                                const c = this.$refs.pad
                                if (!c) return
                                c.getContext('2d').clearRect(0, 0, c.width, c.height)
                                this.dirty = false
                                $wire.set('pod_signature_data', '')
                            },
                            submitPod() {
                                const c = this.$refs.pad
                                if (!this.dirty || !c) {
                                    $wire.set('pod_signature_data', '')
                                } else {
                                    $wire.set('pod_signature_data', c.toDataURL('image/png'))
                                }
                                $wire.markDelivered()
                            },
                        }"
                        x-init="bindCanvas()"
                    >
                        <flux:input wire:model="pod_received_by" :label="__('Received by (optional)')" />
                        <flux:textarea wire:model="pod_note" :label="__('POD note (optional)')" rows="2" />
                        @if (config('logistics.ipod.strict'))
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:input wire:model="pod_latitude" type="text" :label="__('Delivery latitude')" required />
                                <flux:input wire:model="pod_longitude" type="text" :label="__('Delivery longitude')" required />
                            </div>
                            <div>
                                <flux:text class="mb-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Delivery photo') }}</flux:text>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:text-zinc-300 dark:file:bg-zinc-800"
                                    wire:model="pod_photo"
                                />
                                @error('pod_photo')
                                    <flux:text class="mt-1 text-sm text-red-600">{{ $message }}</flux:text>
                                @enderror
                            </div>
                        @else
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:input wire:model="pod_latitude" type="text" :label="__('Delivery latitude (optional)')" />
                                <flux:input wire:model="pod_longitude" type="text" :label="__('Delivery longitude (optional)')" />
                            </div>
                            <div>
                                <flux:text class="mb-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Delivery photo (optional)') }}</flux:text>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:text-zinc-300 dark:file:bg-zinc-800"
                                    wire:model="pod_photo"
                                />
                                @error('pod_photo')
                                    <flux:text class="mt-1 text-sm text-red-600">{{ $message }}</flux:text>
                                @enderror
                            </div>
                        @endif
                        <div class="flex flex-col gap-2">
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ config('logistics.ipod.strict') ? __('Signature (required in strict POD mode)') : __('Signature (optional)') }}
                            </flux:text>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Draw with mouse or finger, then mark delivered.') }}
                            </flux:text>
                            <div
                                wire:ignore
                                class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-600 dark:bg-zinc-900"
                            >
                                <canvas
                                    x-ref="pad"
                                    width="400"
                                    height="160"
                                    class="block max-w-full touch-none cursor-crosshair"
                                ></canvas>
                            </div>
                            <flux:button type="button" variant="ghost" size="sm" x-on:click="clearPad()">
                                {{ __('Clear signature') }}
                            </flux:button>
                        </div>
                        <flux:button type="button" variant="primary" x-on:click="submitPod()">
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
    @endif
</div>
