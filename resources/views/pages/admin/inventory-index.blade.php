<?php

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Inventory')] class extends Component
{
    use WithPagination;

    // Active tab: 'items' | 'stock'
    public string $activeTab = 'items';

    public ?int $editingId = null;

    // Item form
    public string $sku  = '';
    public string $name = '';
    public string $unit = 'kg';

    // Stock form
    public ?int $stockEditingId    = null;
    public string $stockItemId     = '';
    public string $stockWarehouseId = '';
    public string $stockQuantity   = '0';

    // Filters
    public string $filterSearch    = '';
    public string $filterUnit      = '';
    public string $filterWarehouse = '';

    public string $sortColumn    = 'name';
    public string $sortDirection = 'asc';

    public ?int $confirmingDeleteId      = null;
    public ?int $confirmingDeleteStockId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', InventoryItem::class);
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterUnit(): void { $this->resetPage(); }
    public function updatedFilterWarehouse(): void { $this->resetPage(); }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        $allowed = ['sku', 'name', 'unit', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    /**
     * @return array{total_items:int, total_warehouses:int, total_stock_entries:int, low_stock:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'total_items'        => InventoryItem::query()->count(),
            'total_warehouses'   => Warehouse::query()->count(),
            'total_stock_entries' => InventoryStock::query()->count(),
            'low_stock'          => InventoryStock::query()->where('quantity', '<', 10)->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return Warehouse::query()->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InventoryItem>
     */
    #[Computed]
    public function allItems(): \Illuminate\Database\Eloquent\Collection
    {
        return InventoryItem::query()->orderBy('name')->get(['id', 'name', 'sku', 'unit']);
    }

    private function itemQuery(): Builder
    {
        $q = InventoryItem::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term);
            });
        }

        if ($this->filterUnit !== '') {
            $q->where('unit', $this->filterUnit);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection);
    }

    #[Computed]
    public function paginatedItems(): LengthAwarePaginator
    {
        return $this->itemQuery()->paginate(25);
    }

    private function stockQuery(): Builder
    {
        $q = InventoryStock::query()->with(['inventoryItem', 'warehouse']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->whereHas('inventoryItem', function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', $term)->orWhere('sku', 'like', $term);
            });
        }

        if ($this->filterWarehouse !== '') {
            $q->where('warehouse_id', $this->filterWarehouse);
        }

        return $q->orderBy('warehouse_id')->orderByDesc('quantity');
    }

    #[Computed]
    public function paginatedStock(): LengthAwarePaginator
    {
        return $this->stockQuery()->paginate(25);
    }

    // ── Item CRUD ──────────────────────────────────────────────────────────────

    public function startCreate(): void
    {
        Gate::authorize('create', InventoryItem::class);
        $this->resetItemForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $item = InventoryItem::query()->findOrFail($id);
        Gate::authorize('update', $item);

        $this->editingId = $id;
        $this->sku       = $item->sku ?? '';
        $this->name      = $item->name;
        $this->unit      = $item->unit;
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetItemForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'sku'  => ['nullable', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:32'],
        ]);

        $data = [
            'sku'  => $validated['sku'] ?: null,
            'name' => $validated['name'],
            'unit' => $validated['unit'],
        ];

        if ($this->editingId && $this->editingId > 0) {
            $item = InventoryItem::query()->findOrFail($this->editingId);
            Gate::authorize('update', $item);
            $item->update($data);
        } else {
            Gate::authorize('create', InventoryItem::class);
            InventoryItem::query()->create($data);
        }

        $this->editingId = null;
        $this->resetItemForm();
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete-item')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            $item = InventoryItem::query()->findOrFail($this->confirmingDeleteId);
            Gate::authorize('delete', $item);
            $item->delete();
            $this->confirmingDeleteId = null;
            $this->modal('confirm-delete-item')->close();
            $this->resetPage();
        }
    }

    private function resetItemForm(): void
    {
        $this->sku  = '';
        $this->name = '';
        $this->unit = 'kg';
    }

    // ── Stock CRUD ─────────────────────────────────────────────────────────────

    public function startCreateStock(): void
    {
        $this->resetStockForm();
        $this->stockEditingId = 0;
    }

    public function startEditStock(int $id): void
    {
        $stock = InventoryStock::query()->findOrFail($id);

        $this->stockEditingId     = $id;
        $this->stockItemId        = (string) $stock->inventory_item_id;
        $this->stockWarehouseId   = (string) $stock->warehouse_id;
        $this->stockQuantity      = (string) $stock->quantity;
    }

    public function cancelStockForm(): void
    {
        $this->stockEditingId = null;
        $this->resetStockForm();
    }

    public function saveStock(): void
    {
        $validated = $this->validate([
            'stockItemId'      => ['required', 'integer', 'exists:inventory_items,id'],
            'stockWarehouseId' => ['required', 'integer', 'exists:warehouses,id'],
            'stockQuantity'    => ['required', 'numeric', 'min:0'],
        ], [], [
            'stockItemId'      => __('Item'),
            'stockWarehouseId' => __('Warehouse'),
            'stockQuantity'    => __('Quantity'),
        ]);

        $data = [
            'inventory_item_id' => (int) $validated['stockItemId'],
            'warehouse_id'      => (int) $validated['stockWarehouseId'],
            'quantity'          => $validated['stockQuantity'],
        ];

        if ($this->stockEditingId && $this->stockEditingId > 0) {
            $stock = InventoryStock::query()->findOrFail($this->stockEditingId);
            $stock->update(['quantity' => $data['quantity']]);
        } else {
            // Upsert: same item+warehouse combination
            InventoryStock::query()->updateOrCreate(
                [
                    'tenant_id'         => auth()->user()?->tenant_id,
                    'inventory_item_id' => $data['inventory_item_id'],
                    'warehouse_id'      => $data['warehouse_id'],
                ],
                ['quantity' => $data['quantity']],
            );
        }

        $this->stockEditingId = null;
        $this->resetStockForm();
        $this->resetPage();
    }

    public function confirmDeleteStock(int $id): void
    {
        $this->confirmingDeleteStockId = $id;
        $this->modal('confirm-delete-stock')->show();
    }

    public function executeDeleteStock(): void
    {
        if ($this->confirmingDeleteStockId) {
            $stock = InventoryStock::query()->findOrFail($this->confirmingDeleteStockId);
            $stock->delete();
            $this->confirmingDeleteStockId = null;
            $this->modal('confirm-delete-stock')->close();
            $this->resetPage();
        }
    }

    private function resetStockForm(): void
    {
        $this->stockItemId      = '';
        $this->stockWarehouseId = '';
        $this->stockQuantity    = '0';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Inventory')"
        :description="__('Manage inventory items and stock levels per warehouse.')"
    >
        <x-slot name="actions">
            @if ($activeTab === 'items')
                @can('create', \App\Models\InventoryItem::class)
                    <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                        {{ __('New item') }}
                    </flux:button>
                @endcan
            @else
                <flux:button type="button" variant="primary" wire:click="startCreateStock" icon="plus">
                    {{ __('Add stock') }}
                </flux:button>
            @endif
            <flux:button :href="route('admin.warehouse.index')" variant="outline" wire:navigate>
                {{ __('Warehouses') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total items') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total_items'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Warehouses') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total_warehouses'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Stock entries') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total_stock_entries'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4 {{ $this->kpiStats['low_stock'] > 0 ? 'ring-2 ring-red-400' : '' }}">
            <flux:text class="text-sm {{ $this->kpiStats['low_stock'] > 0 ? 'text-red-500' : 'text-zinc-500 dark:text-zinc-400' }}">
                {{ __('Low stock (< 10)') }}
            </flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['low_stock'] > 0 ? 'text-red-600' : '' }}">
                {{ $this->kpiStats['low_stock'] }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="switchTab('items')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'items' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >{{ __('Items') }}</button>
        <button
            wire:click="switchTab('stock')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'stock' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >{{ __('Stock levels') }}</button>
    </div>

    {{-- ═══ ITEMS TAB ════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'items')

        {{-- Item Create / Edit Form --}}
        @if ($editingId !== null)
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">
                    {{ $editingId > 0 ? __('Edit item') : __('New item') }}
                </flux:heading>
                <form wire:submit="save" class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="sku" :label="__('SKU')" :placeholder="__('Optional')" />
                    <flux:input wire:model="name" :label="__('Item name')" required />
                    <flux:select wire:model="unit" :label="__('Unit')">
                        <option value="kg">kg</option>
                        <option value="t">t (ton)</option>
                        <option value="lt">lt (litre)</option>
                        <option value="m">m (metre)</option>
                        <option value="m2">m²</option>
                        <option value="m3">m³</option>
                        <option value="pallet">{{ __('Pallet') }}</option>
                        <option value="box">{{ __('Box') }}</option>
                        <option value="unit">{{ __('Unit') }}</option>
                        <option value="piece">{{ __('Piece') }}</option>
                    </flux:select>
                    <div class="flex flex-wrap gap-2 sm:col-span-3">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </flux:card>
        @endif

        {{-- Filters --}}
        <div class="flex flex-wrap items-end gap-3">
            <flux:input wire:model.live="filterSearch" :label="__('Search')" :placeholder="__('Name or SKU…')" class="max-w-[240px]" />
            <flux:select wire:model.live="filterUnit" :label="__('Unit')" class="max-w-[160px]">
                <option value="">{{ __('All units') }}</option>
                <option value="kg">kg</option>
                <option value="t">t</option>
                <option value="lt">lt</option>
                <option value="m">m</option>
                <option value="pallet">{{ __('Pallet') }}</option>
                <option value="box">{{ __('Box') }}</option>
                <option value="unit">{{ __('Unit') }}</option>
                <option value="piece">{{ __('Piece') }}</option>
            </flux:select>
            @if ($filterSearch !== '' || $filterUnit !== '')
                <div class="flex items-end">
                    <flux:button variant="ghost" size="sm"
                        wire:click="$set('filterSearch', ''); $set('filterUnit', '')">
                        {{ __('Clear') }}
                    </flux:button>
                </div>
            @endif
        </div>

        {{-- Items table --}}
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">
                                <button wire:click="sortBy('sku')" class="flex items-center gap-1 hover:text-zinc-700">
                                    {{ __('SKU') }}
                                    @if ($sortColumn === 'sku') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                </button>
                            </th>
                            <th class="py-2 pe-3 font-medium">
                                <button wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-zinc-700">
                                    {{ __('Item name') }}
                                    @if ($sortColumn === 'name') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                </button>
                            </th>
                            <th class="py-2 pe-3 font-medium">
                                <button wire:click="sortBy('unit')" class="flex items-center gap-1 hover:text-zinc-700">
                                    {{ __('Unit') }}
                                    @if ($sortColumn === 'unit') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                                </button>
                            </th>
                            <th class="py-2 pe-3 text-end font-medium">{{ __('Total stock') }}</th>
                            <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->paginatedItems as $item)
                            @php
                                $totalStock = $item->inventoryStocks()->sum('quantity');
                            @endphp
                            <tr wire:key="item-{{ $item->id }}">
                                <td class="py-2 pe-3 font-mono text-xs text-zinc-500">
                                    {{ $item->sku ?? '—' }}
                                </td>
                                <td class="py-2 pe-3 font-medium">{{ $item->name }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="zinc" size="sm">{{ $item->unit }}</flux:badge>
                                </td>
                                <td class="py-2 pe-3 text-end font-mono font-semibold {{ $totalStock < 10 && $totalStock >= 0 ? 'text-red-600' : '' }}">
                                    {{ number_format((float) $totalStock, 2) }}
                                </td>
                                <td class="py-2 text-end">
                                    @can('update', $item)
                                        <div class="flex justify-end gap-1">
                                            <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $item->id }})">
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $item->id }})">
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-zinc-500">
                                    {{ __('No inventory items found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $this->paginatedItems->links() }}</div>
        </flux:card>

    @endif

    {{-- ═══ STOCK TAB ════════════════════════════════════════════════════════ --}}
    @if ($activeTab === 'stock')

        {{-- Stock Create / Edit Form --}}
        @if ($stockEditingId !== null)
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">
                    {{ $stockEditingId > 0 ? __('Edit stock level') : __('Add stock level') }}
                </flux:heading>
                <form wire:submit="saveStock" class="grid gap-4 sm:grid-cols-3">
                    <flux:select wire:model="stockItemId" :label="__('Item')" :disabled="$stockEditingId > 0" required>
                        <option value="">{{ __('Select item…') }}</option>
                        @foreach ($this->allItems as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku ?? $item->unit }})</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="stockWarehouseId" :label="__('Warehouse')" :disabled="$stockEditingId > 0" required>
                        <option value="">{{ __('Select warehouse…') }}</option>
                        @foreach ($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="stockQuantity" type="number" step="0.0001" min="0" :label="__('Quantity')" required />
                    <div class="flex flex-wrap gap-2 sm:col-span-3">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelStockForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </flux:card>
        @endif

        {{-- Stock Filters --}}
        <div class="flex flex-wrap items-end gap-3">
            <flux:input wire:model.live="filterSearch" :label="__('Search')" :placeholder="__('Item name or SKU…')" class="max-w-[240px]" />
            <flux:select wire:model.live="filterWarehouse" :label="__('Warehouse')" class="max-w-[200px]">
                <option value="">{{ __('All warehouses') }}</option>
                @foreach ($this->warehouses as $wh)
                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                @endforeach
            </flux:select>
            @if ($filterSearch !== '' || $filterWarehouse !== '')
                <div class="flex items-end">
                    <flux:button variant="ghost" size="sm"
                        wire:click="$set('filterSearch', ''); $set('filterWarehouse', '')">
                        {{ __('Clear') }}
                    </flux:button>
                </div>
            @endif
        </div>

        {{-- Stock table --}}
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Item') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('SKU') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Warehouse') }}</th>
                            <th class="py-2 pe-3 text-end font-medium">{{ __('Quantity') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Unit') }}</th>
                            <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->paginatedStock as $stock)
                            <tr wire:key="stock-{{ $stock->id }}"
                                class="{{ (float) $stock->quantity < 10 ? 'bg-red-50 dark:bg-red-950/20' : '' }}">
                                <td class="py-2 pe-3 font-medium">{{ $stock->inventoryItem?->name ?? '—' }}</td>
                                <td class="py-2 pe-3 font-mono text-xs text-zinc-500">
                                    {{ $stock->inventoryItem?->sku ?? '—' }}
                                </td>
                                <td class="py-2 pe-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $stock->warehouse?->name ?? '—' }}
                                </td>
                                <td class="py-2 pe-3 text-end font-mono font-semibold {{ (float) $stock->quantity < 10 ? 'text-red-600' : '' }}">
                                    {{ number_format((float) $stock->quantity, 4) }}
                                </td>
                                <td class="py-2 pe-3">
                                    @if ($stock->inventoryItem)
                                        <flux:badge color="zinc" size="sm">{{ $stock->inventoryItem->unit }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 text-end">
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" wire:click="startEditStock({{ $stock->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmDeleteStock({{ $stock->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-zinc-500">
                                    {{ __('No stock entries found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $this->paginatedStock->links() }}</div>
        </flux:card>

    @endif

    {{-- Confirm delete item modal --}}
    <flux:modal name="confirm-delete-item" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete inventory item?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('All stock entries for this item will also be removed. This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Confirm delete stock modal --}}
    <flux:modal name="confirm-delete-stock" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove stock entry?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeDeleteStock">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
