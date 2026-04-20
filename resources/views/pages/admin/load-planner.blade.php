<?php

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Load Planner')] class extends Component
{
    /** Selected shipment (or order) to plan */
    public ?int $shipmentId = null;

    /** Truck dimensions in "grid units" (1 unit = 0.5 m) */
    public int $truckLength = 26; // 13 m → 26 units

    public int $truckWidth = 5; // 2.5 m → 5 units

    /**
     * Pallet slots placed on the grid.
     * Each item: ['id' => int, 'x' => int, 'y' => int, 'w' => int, 'h' => int, 'label' => string]
     *
     * @var array<int, array{id: int, x: int, y: int, w: int, h: int, label: string}>
     */
    public array $placements = [];

    public int $nextId = 1;

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Shipment> */
    #[Computed]
    public function activeShipments(): \Illuminate\Database\Eloquent\Collection
    {
        return Shipment::query()
            ->with('order.customer', 'vehicle')
            ->whereIn('status', ['planned', 'dispatched'])
            ->latest()
            ->limit(30)
            ->get();
    }

    /** @return array{placed: int, total_units: int, fill_pct: float} */
    #[Computed]
    public function fillStats(): array
    {
        $totalCells   = $this->truckLength * $this->truckWidth;
        $usedCells    = array_sum(array_map(fn ($p) => $p['w'] * $p['h'], $this->placements));

        return [
            'placed'      => count($this->placements),
            'total_units' => $totalCells,
            'used_units'  => $usedCells,
            'fill_pct'    => $totalCells > 0 ? round(($usedCells / $totalCells) * 100, 1) : 0.0,
        ];
    }

    /**
     * Add a standard Euro pallet (80x120 cm → 2×3 units) to the next free slot.
     */
    public function addEuroPallet(): void
    {
        $this->addPallet(2, 3, 'Euro 80×120');
    }

    /**
     * Add an industrial pallet (100x120 cm → 2×3 units rounded).
     */
    public function addIndustrialPallet(): void
    {
        $this->addPallet(2, 3, 'IND 100×120');
    }

    /**
     * Add a BigBag (120x120 cm → 3×3 units).
     */
    public function addBigBag(): void
    {
        $this->addPallet(3, 3, 'BigBag');
    }

    public function removePallet(int $id): void
    {
        $this->placements = array_values(
            array_filter($this->placements, fn ($p) => $p['id'] !== $id)
        );
        unset($this->fillStats);
    }

    public function clearAll(): void
    {
        $this->placements = [];
        $this->nextId     = 1;
        unset($this->fillStats);
    }

    public function movePallet(int $id, int $x, int $y): void
    {
        foreach ($this->placements as &$p) {
            if ($p['id'] === $id) {
                $p['x'] = max(0, min($x, $this->truckLength - $p['w']));
                $p['y'] = max(0, min($y, $this->truckWidth - $p['h']));
                break;
            }
        }
        unset($p, $this->fillStats);
    }

    private function addPallet(int $w, int $h, string $label): void
    {
        // Find first free top-left corner using a simple row scan
        for ($row = 0; $row <= $this->truckWidth - $h; $row++) {
            for ($col = 0; $col <= $this->truckLength - $w; $col++) {
                if (! $this->overlapsAny($col, $row, $w, $h)) {
                    $this->placements[] = [
                        'id'    => $this->nextId++,
                        'x'     => $col,
                        'y'     => $row,
                        'w'     => $w,
                        'h'     => $h,
                        'label' => $label,
                    ];
                    unset($this->fillStats);

                    return;
                }
            }
        }
        // No space found — notify
        $this->dispatch('notify', type: 'error', message: __('No free space for this pallet.'));
    }

    private function overlapsAny(int $x, int $y, int $w, int $h): bool
    {
        foreach ($this->placements as $p) {
            if (
                $x < $p['x'] + $p['w']
                && $x + $w > $p['x']
                && $y < $p['y'] + $p['h']
                && $y + $h > $p['y']
            ) {
                return true;
            }
        }

        return false;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Load Planner')"
        :description="__('2D truck load planner — arrange pallets and monitor fill percentage.')"
    />

    <div class="grid gap-6 lg:grid-cols-[300px_1fr]">

        {{-- Controls panel --}}
        <div class="flex flex-col gap-4">

            {{-- Shipment selector --}}
            <flux:card class="p-4">
                <flux:heading size="sm" class="mb-2">{{ __('Shipment (optional)') }}</flux:heading>
                <flux:select wire:model.live="shipmentId">
                    <option value="">{{ __('— No shipment selected —') }}</option>
                    @foreach ($this->activeShipments as $s)
                        <option value="{{ $s->id }}">
                            {{ $s->vehicle?->plate ?? '—' }} / {{ $s->order?->customer?->legal_name ?? '—' }}
                        </option>
                    @endforeach
                </flux:select>
            </flux:card>

            {{-- Pallet types --}}
            <flux:card class="p-4">
                <flux:heading size="sm" class="mb-3">{{ __('Add item') }}</flux:heading>
                <div class="flex flex-col gap-2">
                    <flux:button wire:click="addEuroPallet" variant="outline" size="sm" class="justify-start">
                        🟦 {{ __('Euro Pallet 80×120') }}
                    </flux:button>
                    <flux:button wire:click="addIndustrialPallet" variant="outline" size="sm" class="justify-start">
                        🟨 {{ __('Industrial 100×120') }}
                    </flux:button>
                    <flux:button wire:click="addBigBag" variant="outline" size="sm" class="justify-start">
                        🟫 {{ __('BigBag 120×120') }}
                    </flux:button>
                </div>
            </flux:card>

            {{-- Truck dimensions --}}
            <flux:card class="p-4">
                <flux:heading size="sm" class="mb-3">{{ __('Truck dimensions') }}</flux:heading>
                <div class="grid grid-cols-2 gap-2">
                    <flux:input
                        wire:model.live="truckLength"
                        type="number"
                        min="10"
                        max="50"
                        :label="__('Length (units)')"
                    />
                    <flux:input
                        wire:model.live="truckWidth"
                        type="number"
                        min="3"
                        max="10"
                        :label="__('Width (units)')"
                    />
                </div>
                <flux:text class="mt-1 text-xs text-zinc-400">{{ __('1 unit = 0.5 m') }}</flux:text>
            </flux:card>

            {{-- Fill stats --}}
            <flux:card class="p-4">
                <flux:heading size="sm" class="mb-3">{{ __('Fill statistics') }}</flux:heading>
                @php $stats = $this->fillStats; @endphp
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">{{ __('Pallets placed') }}</span>
                        <span class="font-semibold">{{ $stats['placed'] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">{{ __('Fill') }}</span>
                        <span class="font-semibold {{ $stats['fill_pct'] > 90 ? 'text-red-500' : ($stats['fill_pct'] > 70 ? 'text-orange-500' : 'text-green-600') }}">
                            {{ $stats['fill_pct'] }}%
                        </span>
                    </div>
                    {{-- Progress bar --}}
                    <div class="h-3 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div
                            class="h-3 rounded-full transition-all {{ $stats['fill_pct'] > 90 ? 'bg-red-500' : ($stats['fill_pct'] > 70 ? 'bg-orange-400' : 'bg-green-500') }}"
                            style="width: {{ min($stats['fill_pct'], 100) }}%"
                        ></div>
                    </div>
                    @if ($stats['fill_pct'] > 95)
                        <p class="text-xs font-medium text-red-500">⚠ {{ __('Truck is nearly full!') }}</p>
                    @endif
                </div>
                <div class="mt-3">
                    <flux:button wire:click="clearAll" variant="ghost" size="sm" class="text-red-500 hover:text-red-600">
                        {{ __('Clear all') }}
                    </flux:button>
                </div>
            </flux:card>
        </div>

        {{-- Truck grid --}}
        <flux:card class="overflow-auto p-4">
            <flux:heading size="sm" class="mb-3">{{ __('Truck floor view (bird\'s eye)') }}</flux:heading>

            <div
                class="relative inline-grid border-2 border-zinc-800 dark:border-zinc-300"
                style="
                    display: grid;
                    grid-template-columns: repeat({{ $this->truckLength }}, 20px);
                    grid-template-rows: repeat({{ $this->truckWidth }}, 20px);
                    width: {{ $this->truckLength * 20 }}px;
                    height: {{ $this->truckWidth * 20 }}px;
                    background:
                        repeating-linear-gradient(#e4e4e7 0 1px, transparent 1px 20px),
                        repeating-linear-gradient(90deg, #e4e4e7 0 1px, transparent 1px 20px);
                "
                x-data="{
                    dragging: null,
                    startCell: null,
                    onDragStart(id, event) {
                        this.dragging = id;
                    },
                    onDrop(event) {
                        if (!this.dragging) return;
                        const rect = this.$el.getBoundingClientRect();
                        const x = Math.floor((event.clientX - rect.left) / 20);
                        const y = Math.floor((event.clientY - rect.top) / 20);
                        $wire.movePallet(this.dragging, x, y);
                        this.dragging = null;
                    }
                }"
                @dragover.prevent
                @drop="onDrop($event)"
            >
                {{-- Placed pallets --}}
                @foreach ($this->placements as $pallet)
                    @php
                        $colors = [
                            'Euro 80×120'   => 'bg-blue-300 border-blue-500 text-blue-900',
                            'IND 100×120'   => 'bg-yellow-300 border-yellow-500 text-yellow-900',
                            'BigBag'        => 'bg-amber-700 border-amber-900 text-white',
                        ];
                        $color = $colors[$pallet['label']] ?? 'bg-zinc-300 border-zinc-500 text-zinc-900';
                    @endphp
                    <div
                        class="absolute flex cursor-grab items-center justify-center overflow-hidden rounded border-2 text-center text-xs font-bold {{ $color }}"
                        style="
                            left: {{ $pallet['x'] * 20 }}px;
                            top: {{ $pallet['y'] * 20 }}px;
                            width: {{ $pallet['w'] * 20 - 2 }}px;
                            height: {{ $pallet['h'] * 20 - 2 }}px;
                        "
                        draggable="true"
                        @dragstart="onDragStart({{ $pallet['id'] }}, $event)"
                        title="{{ $pallet['label'] }}"
                    >
                        <span class="leading-none">{{ $pallet['label'] }}</span>
                    </div>
                @endforeach

                {{-- Empty state --}}
                @if (empty($this->placements))
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-xs text-zinc-400">
                        {{ __('Click "Add item" to place pallets') }}
                    </div>
                @endif
            </div>

            <p class="mt-2 text-xs text-zinc-400">
                {{ __('Truck') }}: {{ number_format($this->truckLength * 0.5, 1) }} m × {{ number_format($this->truckWidth * 0.5, 1) }} m
                · {{ __('drag pallets to reposition') }}
            </p>
        </flux:card>

    </div>
</div>
