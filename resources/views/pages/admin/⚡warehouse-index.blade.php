<?php

use App\Authorization\LogisticsPermission;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Warehouse')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithPagination;

    public ?int $warehouseEditingId = null;

    public string $warehouse_code = '';

    public string $warehouse_name = '';

    public string $warehouse_address = '';

    public ?int $itemEditingId = null;

    public string $item_sku = '';

    public string $item_name = '';

    public string $item_unit = 'kg';

    public ?int $stockEditingId = null;

    public string $stock_warehouse_id = '';

    public string $stock_inventory_item_id = '';

    public string $stock_quantity = '0';

    public function mount(): void
    {
        Gate::authorize('viewAny', Warehouse::class);
    }

    /**
     * @return LengthAwarePaginator<int, Warehouse>
     */
    #[Computed]
    public function paginatedWarehouses(): LengthAwarePaginator
    {
        return Warehouse::query()->orderBy('code')->paginate(10, pageName: 'wh');
    }

    /**
     * @return LengthAwarePaginator<int, InventoryItem>
     */
    #[Computed]
    public function paginatedItems(): LengthAwarePaginator
    {
        return InventoryItem::query()->orderBy('sku')->paginate(10, pageName: 'it');
    }

    /**
     * @return LengthAwarePaginator<int, InventoryStock>
     */
    #[Computed]
    public function paginatedStocks(): LengthAwarePaginator
    {
        return InventoryStock::query()
            ->with(['warehouse', 'inventoryItem'])
            ->orderByDesc('id')
            ->paginate(12, pageName: 'st');
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function allWarehousesForSelect(): Collection
    {
        return Warehouse::query()->orderBy('code')->get();
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    #[Computed]
    public function allItemsForSelect(): Collection
    {
        return InventoryItem::query()->orderBy('sku')->get();
    }

    public function startCreateWarehouse(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        Gate::authorize('create', Warehouse::class);
        $this->resetWarehouseForm();
        $this->warehouseEditingId = 0;
    }

    public function startEditWarehouse(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        $row = Warehouse::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->warehouseEditingId = $row->id;
        $this->warehouse_code = $row->code;
        $this->warehouse_name = $row->name;
        $this->warehouse_address = (string) ($row->address ?? '');
    }

    public function cancelWarehouseForm(): void
    {
        $this->warehouseEditingId = null;
        $this->resetWarehouseForm();
    }

    public function saveWarehouse(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);

        $tenantId = (int) auth()->user()->tenant_id;
        $warehouseUnique = Rule::unique('warehouses', 'code')->where('tenant_id', $tenantId);
        if ($this->warehouseEditingId !== null && $this->warehouseEditingId > 0) {
            $warehouseUnique = $warehouseUnique->ignore($this->warehouseEditingId);
        }

        $validated = $this->validate([
            'warehouse_code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/', $warehouseUnique],
            'warehouse_name' => ['required', 'string', 'max:255'],
            'warehouse_address' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'warehouse_code' => __('Code'),
            'warehouse_name' => __('Name'),
            'warehouse_address' => __('Address'),
        ]);

        $data = [
            'code' => strtoupper($validated['warehouse_code']),
            'name' => $validated['warehouse_name'],
            'address' => $validated['warehouse_address'] !== '' ? $validated['warehouse_address'] : null,
        ];

        if ($this->warehouseEditingId !== null && $this->warehouseEditingId > 0) {
            $row = Warehouse::query()->findOrFail($this->warehouseEditingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', Warehouse::class);
            Warehouse::query()->create($data);
        }

        $this->warehouseEditingId = null;
        $this->resetWarehouseForm();
        unset($this->paginatedWarehouses, $this->allWarehousesForSelect);
    }

    public function deleteWarehouse(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        $row = Warehouse::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        unset($this->paginatedWarehouses, $this->paginatedStocks, $this->allWarehousesForSelect);
    }

    private function resetWarehouseForm(): void
    {
        $this->warehouse_code = '';
        $this->warehouse_name = '';
        $this->warehouse_address = '';
    }

    public function startCreateItem(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        Gate::authorize('create', InventoryItem::class);
        $this->resetItemForm();
        $this->itemEditingId = 0;
    }

    public function startEditItem(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        $row = InventoryItem::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->itemEditingId = $row->id;
        $this->item_sku = $row->sku;
        $this->item_name = $row->name;
        $this->item_unit = $row->unit;
    }

    public function cancelItemForm(): void
    {
        $this->itemEditingId = null;
        $this->resetItemForm();
    }

    public function saveItem(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);

        $tenantId = (int) auth()->user()->tenant_id;
        $skuUnique = Rule::unique('inventory_items', 'sku')->where('tenant_id', $tenantId);
        if ($this->itemEditingId !== null && $this->itemEditingId > 0) {
            $skuUnique = $skuUnique->ignore($this->itemEditingId);
        }

        $validated = $this->validate([
            'item_sku' => ['required', 'string', 'max:128', $skuUnique],
            'item_name' => ['required', 'string', 'max:255'],
            'item_unit' => ['required', 'string', 'max:32'],
        ], [], [
            'item_sku' => __('SKU'),
            'item_name' => __('Name'),
            'item_unit' => __('Unit'),
        ]);

        $data = [
            'sku' => strtoupper(trim($validated['item_sku'])),
            'name' => $validated['item_name'],
            'unit' => $validated['item_unit'],
        ];

        if ($this->itemEditingId !== null && $this->itemEditingId > 0) {
            $row = InventoryItem::query()->findOrFail($this->itemEditingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', InventoryItem::class);
            InventoryItem::query()->create($data);
        }

        $this->itemEditingId = null;
        $this->resetItemForm();
        unset($this->paginatedItems, $this->allItemsForSelect);
    }

    public function deleteItem(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        $row = InventoryItem::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        unset($this->paginatedItems, $this->paginatedStocks, $this->allItemsForSelect);
    }

    private function resetItemForm(): void
    {
        $this->item_sku = '';
        $this->item_name = '';
        $this->item_unit = 'kg';
    }

    public function startCreateStock(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        Gate::authorize('create', InventoryStock::class);
        $this->resetStockForm();
        $this->stockEditingId = 0;
    }

    public function startEditStock(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        $row = InventoryStock::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->stockEditingId = $row->id;
        $this->stock_warehouse_id = (string) $row->warehouse_id;
        $this->stock_inventory_item_id = (string) $row->inventory_item_id;
        $this->stock_quantity = (string) $row->quantity;
    }

    public function cancelStockForm(): void
    {
        $this->stockEditingId = null;
        $this->resetStockForm();
    }

    public function saveStock(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);

        $validated = $this->validate([
            'stock_warehouse_id' => ['required', 'exists:warehouses,id'],
            'stock_inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'stock_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], [], [
            'stock_warehouse_id' => __('Warehouse'),
            'stock_inventory_item_id' => __('Stock item'),
            'stock_quantity' => __('Quantity'),
        ]);

        $warehouse = Warehouse::query()->findOrFail((int) $validated['stock_warehouse_id']);
        Gate::authorize('view', $warehouse);
        $item = InventoryItem::query()->findOrFail((int) $validated['stock_inventory_item_id']);
        Gate::authorize('view', $item);

        $qty = (string) $validated['stock_quantity'];

        if ($this->stockEditingId !== null && $this->stockEditingId > 0) {
            $row = InventoryStock::query()->findOrFail($this->stockEditingId);
            Gate::authorize('update', $row);
            if ((int) $row->warehouse_id !== (int) $validated['stock_warehouse_id']
                || (int) $row->inventory_item_id !== (int) $validated['stock_inventory_item_id']) {
                $dup = InventoryStock::query()
                    ->where('warehouse_id', (int) $validated['stock_warehouse_id'])
                    ->where('inventory_item_id', (int) $validated['stock_inventory_item_id'])
                    ->where('id', '!=', $row->id)
                    ->exists();
                if ($dup) {
                    $this->addError('stock_warehouse_id', __('A stock row already exists for this warehouse and item.'));

                    return;
                }
            }
            $row->update([
                'warehouse_id' => (int) $validated['stock_warehouse_id'],
                'inventory_item_id' => (int) $validated['stock_inventory_item_id'],
                'quantity' => $qty,
            ]);
        } else {
            Gate::authorize('create', InventoryStock::class);
            $existing = InventoryStock::query()
                ->where('warehouse_id', (int) $validated['stock_warehouse_id'])
                ->where('inventory_item_id', (int) $validated['stock_inventory_item_id'])
                ->first();
            if ($existing !== null) {
                Gate::authorize('update', $existing);
                $existing->update(['quantity' => $qty]);
            } else {
                InventoryStock::query()->create([
                    'warehouse_id' => (int) $validated['stock_warehouse_id'],
                    'inventory_item_id' => (int) $validated['stock_inventory_item_id'],
                    'quantity' => $qty,
                ]);
            }
        }

        $this->stockEditingId = null;
        $this->resetStockForm();
        unset($this->paginatedStocks);
    }

    public function deleteStock(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::WAREHOUSE_WRITE);
        $row = InventoryStock::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        unset($this->paginatedStocks);
    }

    private function resetStockForm(): void
    {
        $this->stock_warehouse_id = '';
        $this->stock_inventory_item_id = '';
        $this->stock_quantity = '0';
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteWarehouse =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::WAREHOUSE_WRITE);
    @endphp

    <x-admin.page-header
        :heading="__('Warehouse')"
        :description="__('Warehouses, stock cards, and per-location balances (MVP).')"
    />

    <flux:card class="p-4">
        <flux:heading size="lg" class="mb-4">{{ __('Warehouses') }}</flux:heading>

        @if ($canWriteWarehouse)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                @if ($warehouseEditingId === null)
                    <flux:button type="button" variant="primary" wire:click="startCreateWarehouse">{{ __('New warehouse') }}</flux:button>
                @endif
            </div>

            @if ($warehouseEditingId !== null)
                <form wire:submit="saveWarehouse" class="mb-6 grid max-w-xl gap-4">
                    <flux:input wire:model="warehouse_code" :label="__('Code')" required />
                    <flux:input wire:model="warehouse_name" :label="__('Name')" required />
                    <flux:textarea wire:model="warehouse_address" :label="__('Address')" rows="3" />
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelWarehouseForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @endif
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-4 font-medium text-zinc-800 dark:text-white">{{ __('Code') }}</th>
                        <th class="py-2 pe-4 font-medium text-zinc-800 dark:text-white">{{ __('Name') }}</th>
                        <th class="py-2 pe-4">{{ __('Address') }}</th>
                        @if ($canWriteWarehouse)
                            <th class="py-2 text-end">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedWarehouses as $w)
                        <tr>
                            <td class="py-2 pe-4 font-mono text-xs">{{ $w->code }}</td>
                            <td class="py-2 pe-4">{{ $w->name }}</td>
                            <td class="py-2 pe-4 text-zinc-600 dark:text-zinc-400">{{ $w->address ? \Illuminate\Support\Str::limit($w->address, 60) : '—' }}</td>
                            @if ($canWriteWarehouse)
                                <td class="py-2 text-end">
                                    <flux:button size="sm" variant="ghost" wire:click="startEditWarehouse({{ $w->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="deleteWarehouse({{ $w->id }})"
                                        wire:confirm="{{ __('Delete this warehouse? Stock rows in this warehouse will be removed.') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-zinc-500">{{ __('No warehouses yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->paginatedWarehouses->links() }}
        </div>
    </flux:card>

    <flux:card class="p-4">
        <flux:heading size="lg" class="mb-4">{{ __('Stock items') }}</flux:heading>

        @if ($canWriteWarehouse)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                @if ($itemEditingId === null)
                    <flux:button type="button" variant="primary" wire:click="startCreateItem">{{ __('New stock item') }}</flux:button>
                @endif
            </div>

            @if ($itemEditingId !== null)
                <form wire:submit="saveItem" class="mb-6 grid max-w-xl gap-4">
                    <flux:input wire:model="item_sku" :label="__('SKU')" required />
                    <flux:input wire:model="item_name" :label="__('Name')" required />
                    <flux:input wire:model="item_unit" :label="__('Unit')" required />
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelItemForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @endif
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-4 font-medium text-zinc-800 dark:text-white">{{ __('SKU') }}</th>
                        <th class="py-2 pe-4 font-medium text-zinc-800 dark:text-white">{{ __('Name') }}</th>
                        <th class="py-2 pe-4">{{ __('Unit') }}</th>
                        @if ($canWriteWarehouse)
                            <th class="py-2 text-end">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedItems as $it)
                        <tr>
                            <td class="py-2 pe-4 font-mono text-xs">{{ $it->sku }}</td>
                            <td class="py-2 pe-4">{{ $it->name }}</td>
                            <td class="py-2 pe-4">{{ $it->unit }}</td>
                            @if ($canWriteWarehouse)
                                <td class="py-2 text-end">
                                    <flux:button size="sm" variant="ghost" wire:click="startEditItem({{ $it->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="deleteItem({{ $it->id }})"
                                        wire:confirm="{{ __('Delete this stock item? Related stock balances will be removed.') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-zinc-500">{{ __('No stock items yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->paginatedItems->links() }}
        </div>
    </flux:card>

    <flux:card class="p-4">
        <flux:heading size="lg" class="mb-4">{{ __('Stock balances') }}</flux:heading>
        <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Quantity per warehouse and item. Saving an existing pair updates the quantity.') }}
        </flux:text>

        @if ($canWriteWarehouse)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                @if ($stockEditingId === null)
                    <flux:button type="button" variant="primary" wire:click="startCreateStock">{{ __('Set stock balance') }}</flux:button>
                @endif
            </div>

            @if ($stockEditingId !== null)
                <form wire:submit="saveStock" class="mb-6 grid max-w-xl gap-4">
                    <flux:select wire:model="stock_warehouse_id" :label="__('Warehouse')" required>
                        <option value="">{{ __('Select warehouse') }}</option>
                        @foreach ($this->allWarehousesForSelect as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->code }} — {{ $wh->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="stock_inventory_item_id" :label="__('Stock item')" required>
                        <option value="">{{ __('Select item') }}</option>
                        @foreach ($this->allItemsForSelect as $it)
                            <option value="{{ $it->id }}">{{ $it->sku }} — {{ $it->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="stock_quantity" :label="__('Quantity')" type="text" required />
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelStockForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @endif
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-4 font-medium text-zinc-800 dark:text-white">{{ __('Warehouse') }}</th>
                        <th class="py-2 pe-4 font-medium text-zinc-800 dark:text-white">{{ __('Item') }}</th>
                        <th class="py-2 pe-4">{{ __('Quantity') }}</th>
                        @if ($canWriteWarehouse)
                            <th class="py-2 text-end">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedStocks as $st)
                        <tr>
                            <td class="py-2 pe-4">{{ $st->warehouse?->code ?? '—' }}</td>
                            <td class="py-2 pe-4 font-mono text-xs">{{ $st->inventoryItem?->sku ?? '—' }}</td>
                            <td class="py-2 pe-4">{{ number_format((float) $st->quantity, 4) }}</td>
                            @if ($canWriteWarehouse)
                                <td class="py-2 text-end">
                                    <flux:button size="sm" variant="ghost" wire:click="startEditStock({{ $st->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="deleteStock({{ $st->id }})"
                                        wire:confirm="{{ __('Delete this stock row?') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-zinc-500">{{ __('No stock balances yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->paginatedStocks->links() }}
        </div>
    </flux:card>
</div>
