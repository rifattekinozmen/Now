<?php

use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Warehouse detail')] class extends Component
{
    public Warehouse $warehouse;

    public function mount(Warehouse $warehouse): void
    {
        Gate::authorize('view', $warehouse);
        $this->warehouse = $warehouse;
    }

    /**
     * @return array{total_items:int, total_quantity:float, low_stock:int}
     */
    #[Computed]
    public function stats(): array
    {
        $stocks = InventoryStock::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->get();

        return [
            'total_items'    => $stocks->count(),
            'total_quantity' => (float) $stocks->sum('quantity'),
            'low_stock'      => $stocks->filter(fn ($s) => (float) $s->quantity < 10)->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InventoryStock>
     */
    #[Computed]
    public function stocks(): \Illuminate\Database\Eloquent\Collection
    {
        return InventoryStock::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->with('inventoryItem')
            ->orderBy('quantity', 'desc')
            ->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header :heading="$this->warehouse->name">
        <x-slot name="actions">
            <flux:button :href="route('admin.inventory.index')" variant="outline" wire:navigate>
                {{ __('Inventory') }}
            </flux:button>
            <flux:button :href="route('admin.warehouse.index')" variant="ghost" wire:navigate>
                {{ __('Back to warehouses') }}
            </flux:button>
        </x-slot>
        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            @if ($this->warehouse->code)
                <span class="font-mono">{{ $this->warehouse->code }}</span> —
            @endif
            {{ $this->warehouse->address ?? __('No address') }}
        </flux:text>
    </x-admin.page-header>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Distinct items') }}</flux:text>
            <flux:heading size="lg">{{ $this->stats['total_items'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total quantity (all items)') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->stats['total_quantity'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4 {{ $this->stats['low_stock'] > 0 ? 'ring-2 ring-red-400' : '' }}">
            <flux:text class="text-sm {{ $this->stats['low_stock'] > 0 ? 'text-red-500' : 'text-zinc-500 dark:text-zinc-400' }}">
                {{ __('Low stock (< 10)') }}
            </flux:text>
            <flux:heading size="lg" class="{{ $this->stats['low_stock'] > 0 ? 'text-red-600' : '' }}">
                {{ $this->stats['low_stock'] }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Stock table --}}
    <flux:card class="p-4">
        <flux:heading size="lg" class="mb-4">{{ __('Stock balances') }}</flux:heading>
        @if ($this->stocks->isEmpty())
            <div class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No stock entries for this warehouse yet.') }}
                <div class="mt-3">
                    <flux:button :href="route('admin.inventory.index')" variant="outline" size="sm" wire:navigate>
                        {{ __('Go to inventory') }}
                    </flux:button>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('SKU') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Item name') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Unit') }}</th>
                            <th class="py-2 text-end font-medium">{{ __('Quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->stocks as $stock)
                            <tr wire:key="ws-{{ $stock->id }}"
                                class="{{ (float) $stock->quantity < 10 ? 'bg-red-50 dark:bg-red-950/20' : '' }}">
                                <td class="py-2 pe-3 font-mono text-xs text-zinc-500">
                                    {{ $stock->inventoryItem?->sku ?? '—' }}
                                </td>
                                <td class="py-2 pe-3 font-medium">
                                    {{ $stock->inventoryItem?->name ?? '—' }}
                                </td>
                                <td class="py-2 pe-3">
                                    @if ($stock->inventoryItem)
                                        <flux:badge color="zinc" size="sm">{{ $stock->inventoryItem->unit }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 text-end font-mono font-semibold {{ (float) $stock->quantity < 10 ? 'text-red-600' : '' }}">
                                    {{ number_format((float) $stock->quantity, 4) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td colspan="3" class="py-2 pe-3 text-xs text-zinc-400">
                                {{ __(':count items', ['count' => $this->stocks->count()]) }}
                            </td>
                            <td class="py-2 text-end font-mono text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                                {{ number_format((float) $this->stocks->sum('quantity'), 4) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </flux:card>
</div>
