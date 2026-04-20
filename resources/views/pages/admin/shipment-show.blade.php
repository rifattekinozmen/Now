<?php

use App\Authorization\LogisticsPermission;
use App\Enums\ShipmentStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Jobs\SendUetdsNotificationJob;
use App\Services\Logistics\PodDeliveryPhotoStorage;
use App\Services\Logistics\ShipmentStatusTransitionService;
use App\Services\Logistics\UetdsNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    // Return / Damage tab fields
    public bool $returnIsReturn = false;
    public string $returnReason = '';
    /** @var mixed */
    public $returnPhoto = null;

    // Reassignment fields
    public string $editVehicleId = '';
    public string $editDriverId = '';

    public function mount(Shipment $shipment): void
    {
        Gate::authorize('view', $shipment);
        $this->shipment = $shipment->load(['order.customer', 'vehicle', 'driver']);
        $this->returnIsReturn = (bool) $shipment->is_return;
        $this->returnReason  = $shipment->return_reason ?? '';
        $this->editVehicleId = (string) ($shipment->vehicle_id ?? '');
        $this->editDriverId  = (string) ($shipment->driver_employee_id ?? '');
    }

    /**
     * @return array<int, array{id: int, plate: string}>
     */
    public function vehicleOptions(): array
    {
        $tenantId = auth()->user()?->tenant_id ?? 0;

        return Cache::remember("vehicle-options.{$tenantId}", 300, function () {
            return Vehicle::query()
                ->orderBy('plate')
                ->limit(500)
                ->get()
                ->map(fn (Vehicle $v) => ['id' => $v->id, 'plate' => $v->plate])
                ->all();
        });
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function driverOptions(): array
    {
        $tenantId = auth()->user()?->tenant_id ?? 0;

        return Cache::remember("driver-options.{$tenantId}", 300, function () {
            return Employee::query()
                ->where('is_driver', true)
                ->orderBy('last_name')
                ->limit(500)
                ->get()
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->fullName()])
                ->all();
        });
    }

    public function reassignVehicleDriver(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::SHIPMENTS_WRITE);

        $s = Shipment::query()->findOrFail($this->shipment->id);
        Gate::authorize('update', $s);

        $tenantId = auth()->user()?->tenant_id;

        $validated = $this->validate([
            'editVehicleId' => [
                'nullable',
                'integer',
                $tenantId !== null
                    ? Rule::exists('vehicles', 'id')->where('tenant_id', $tenantId)
                    : Rule::exists('vehicles', 'id'),
            ],
            'editDriverId' => [
                'nullable',
                'integer',
                $tenantId !== null
                    ? Rule::exists('employees', 'id')->where('tenant_id', $tenantId)
                    : Rule::exists('employees', 'id'),
            ],
        ]);

        $s->update([
            'vehicle_id'         => filled($validated['editVehicleId']) ? (int) $validated['editVehicleId'] : null,
            'driver_employee_id' => filled($validated['editDriverId']) ? (int) $validated['editDriverId'] : null,
        ]);

        $this->shipment = $s->fresh()->load(['order.customer', 'vehicle', 'driver']);

        session()->flash('success', __('Assignment updated.'));
    }

    public function saveReturn(): void
    {
        Gate::authorize('update', $this->shipment);

        $validated = $this->validate([
            'returnIsReturn' => ['boolean'],
            'returnReason'   => ['nullable', 'string', 'max:1000'],
            'returnPhoto'    => ['nullable', 'image', 'max:5120'],
        ]);

        $data = [
            'is_return'     => $validated['returnIsReturn'],
            'return_reason' => $validated['returnIsReturn'] && filled($validated['returnReason'])
                ? $validated['returnReason']
                : null,
        ];

        if ($this->returnPhoto) {
            $data['return_photo_path'] = $this->returnPhoto->store('shipment-returns', 'local');
            $this->returnPhoto = null;
        }

        $this->shipment->update($data);
        $this->shipment->refresh();

        session()->flash('success', __('Return information saved.'));
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

        $this->shipment = $s->fresh()->load(['order.customer', 'vehicle', 'driver']);
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
        $this->shipment = $s->fresh()->load(['order.customer', 'vehicle', 'driver']);
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

        $this->shipment = $s->fresh()->load(['order.customer', 'vehicle', 'driver']);
    }

    public function setShipmentTab(string $tab): void
    {
        $allowed = ['overview', 'tracking', 'timeline', 'operations', 'return', 'documents', 'legal'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

    public function submitUetds(UetdsNotificationService $service): void
    {
        Gate::authorize('update', $this->shipment);
        $this->shipment->loadMissing(['order', 'vehicle', 'driver']);
        $service->notify($this->shipment);
        $this->shipment->refresh();
    }

    public function resubmitUetds(): void
    {
        Gate::authorize('update', $this->shipment);
        SendUetdsNotificationJob::dispatch($this->shipment->id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    #[\Livewire\Attributes\Computed]
    public function shipmentDocuments(): \Illuminate\Database\Eloquent\Collection
    {
        return Document::query()
            ->where('documentable_type', Shipment::class)
            ->where('documentable_id', $this->shipment->id)
            ->orderByDesc('created_at')
            ->get();
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
        <flux:button type="button" size="sm"
            :variant="$activeTab === 'return' ? 'danger' : 'ghost'"
            wire:click="setShipmentTab('return')"
        >
            @if ($s->is_return)
                <span class="mr-1 inline-block size-2 rounded-full bg-red-500"></span>
            @endif
            {{ __('Return / Damage') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'documents' ? 'primary' : 'ghost'" wire:click="setShipmentTab('documents')">
            {{ __('Documents') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$activeTab === 'legal' ? 'primary' : 'ghost'" wire:click="setShipmentTab('legal')">
            {{ __('Legal') }}
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
    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
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
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Driver') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $s->driver?->fullName() ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('SAS / reference') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $s->order?->sas_no ?? '—' }}</dd>
            </div>
        </dl>
    </flux:card>

    @if ($canWriteShipments && $s->status !== \App\Enums\ShipmentStatus::Cancelled)
    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Reassign vehicle / driver') }}</flux:heading>
        <form wire:submit="reassignVehicleDriver" class="flex max-w-md flex-col gap-4">
            <flux:select wire:model="editVehicleId" :label="__('Vehicle')">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach ($this->vehicleOptions() as $v)
                    <flux:select.option :value="$v['id']">{{ $v['plate'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="editDriverId" :label="__('Driver')">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach ($this->driverOptions() as $d)
                    <flux:select.option :value="$d['id']">{{ $d['name'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">{{ __('Save assignment') }}</flux:button>
        </form>
    </flux:card>
    @endif
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

    {{-- ═══════════════════════════════════════ --}}
    {{-- Return / Damage Tab --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeTab === 'return')
    <flux:card class="p-6">
        <div class="flex items-center gap-3 mb-6">
            <flux:heading size="lg">{{ __('Return / Damage') }}</flux:heading>
            @if ($s->is_return)
                <flux:badge color="red">{{ __('Marked as return') }}</flux:badge>
            @endif
        </div>

        @php
            $canManageReturn = auth()->user()?->can(\App\Authorization\LogisticsPermission::ADMIN);
        @endphp

        @if ($canManageReturn)
        <form wire:submit="saveReturn" class="flex flex-col gap-4 max-w-lg">
            <flux:field>
                <div class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="is_return_toggle"
                        wire:model.live="returnIsReturn"
                        class="h-4 w-4 rounded border-gray-300 text-primary"
                    />
                    <label for="is_return_toggle" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Mark as return / damaged delivery') }}
                    </label>
                </div>
            </flux:field>

            @if ($returnIsReturn)
            <flux:field>
                <flux:label>{{ __('Return reason') }}</flux:label>
                <flux:textarea wire:model="returnReason" rows="3"
                    :placeholder="__('Describe the reason: carrier error, product damage, wetness, etc.')" />
                <flux:error name="returnReason" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Return photo') }}</flux:label>
                @if ($s->return_photo_path)
                    <div class="mb-2">
                        <flux:badge color="green" size="sm">{{ __('Photo uploaded') }}</flux:badge>
                        <span class="text-xs text-zinc-400 ml-1">{{ basename($s->return_photo_path) }}</span>
                    </div>
                @endif
                <input type="file" wire:model="returnPhoto" accept="image/*"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium" />
                <flux:error name="returnPhoto" />
            </flux:field>
            @endif

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
        @else
            {{-- Read-only view --}}
            @if ($s->is_return)
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Return reason') }}</dt>
                        <dd class="font-medium">{{ $s->return_reason ?? '—' }}</dd>
                    </div>
                    @if ($s->return_photo_path)
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('Return photo') }}</dt>
                        <dd><flux:badge color="green" size="sm">{{ __('Photo uploaded') }}</flux:badge></dd>
                    </div>
                    @endif
                </dl>
            @else
                <flux:text class="text-zinc-400">{{ __('No return recorded for this shipment.') }}</flux:text>
            @endif
        @endif
    </flux:card>
    @endif

    {{-- TAB: Documents --}}
    @if ($activeTab === 'documents')
        <flux:card class="p-4">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                <flux:button :href="route('admin.documents.index')" size="sm" variant="ghost" wire:navigate>
                    {{ __('Manage all') }}
                </flux:button>
            </div>
            @if ($this->shipmentDocuments->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No documents for this shipment yet.') }}</flux:text>
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
                            @foreach ($this->shipmentDocuments as $doc)
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

    {{-- Legal Notifications Tab --}}
    @if ($activeTab === 'legal')
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Legal Notifications') }}</flux:heading>

            @php
                $uetdsEnabled = config('logistics.uetds.enabled', false);
                $uetdsMeta    = $shipment->meta['uetds'] ?? null;
            @endphp

            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div>
                        <p class="font-semibold text-zinc-800 dark:text-zinc-100">U-ETDS</p>
                        <p class="text-sm text-zinc-500">{{ __('Ulusal Elektronik Tebligat Dağıtım Sistemi — sefer bildirimi') }}</p>

                        @if ($uetdsMeta)
                            <div class="mt-2 space-y-1 text-sm">
                                <p>{{ __('Reference') }}: <span class="font-mono">{{ $uetdsMeta['reference_no'] ?? '—' }}</span></p>
                                <p>{{ __('Submitted') }}: {{ isset($uetdsMeta['submitted_at']) ? \Illuminate\Support\Carbon::parse($uetdsMeta['submitted_at'])->format('d M Y H:i') : '—' }}</p>
                                <flux:badge color="green" size="sm">{{ $uetdsMeta['status'] ?? 'unknown' }}</flux:badge>
                            </div>
                        @else
                            <p class="mt-2 text-sm text-zinc-400">{{ __('Not yet submitted.') }}</p>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2">
                        @if ($uetdsEnabled)
                            @if (! $uetdsMeta)
                                <flux:button size="sm" variant="primary" wire:click="submitUetds">
                                    {{ __('Submit Now') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" wire:click="resubmitUetds">
                                    {{ __('Resubmit') }}
                                </flux:button>
                            @endif
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('U-ETDS Disabled') }}</flux:badge>
                            <p class="text-xs text-zinc-400">{{ __('Enable via UETDS_ENABLED=true') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </flux:card>
    @endif
</div>
