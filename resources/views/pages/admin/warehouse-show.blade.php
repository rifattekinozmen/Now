<?php

use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Warehouse detail')] class extends Component
{
    public Warehouse $warehouse;

    public function mount(Warehouse $warehouse): void
    {
        Gate::authorize('view', $warehouse);
        $this->warehouse = $warehouse->load([
            'inventoryStocks' => fn ($q) => $q->orderBy('id')->with('inventoryItem'),
        ]);
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Warehouse detail')">
        <x-slot name="actions">
            <flux:button :href="route('admin.warehouse.index')" variant="ghost" wire:navigate>
                {{ __('Back to warehouse') }}
            </flux:button>
        </x-slot>
        <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ $this->warehouse->code }} — {{ $this->warehouse->name }}
        </flux:text>
    </x-admin.page-header>

    <flux:card>
        <flux:heading size="lg" class="mb-2">{{ __('Address') }}</flux:heading>
        <flux:text class="text-sm text-zinc-700 dark:text-zinc-300">
            {{ $this->warehouse->address !== null && trim((string) $this->warehouse->address) !== '' ? $this->warehouse->address : '—' }}
        </flux:text>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Stock balances at this warehouse') }}</flux:heading>
        @if ($this->warehouse->inventoryStocks->isEmpty())
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No stock rows for this warehouse yet.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('SKU') }}</flux:table.column>
                    <flux:table.column>{{ __('Item') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->warehouse->inventoryStocks as $st)
                        <flux:table.row wire:key="wh-st-{{ $st->id }}">
                            <flux:table.cell class="font-mono text-xs">{{ $st->inventoryItem?->sku ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $st->inventoryItem?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format((float) $st->quantity, 4) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
