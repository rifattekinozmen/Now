<?php

use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Warehouse')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Warehouse')">
        <x-slot name="breadcrumb">
            <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ __('Warehouse') }}</span>
        </x-slot>
    </x-admin.page-header>

    <flux:card>
        <flux:heading size="lg" class="mb-2">{{ __('Inventory & stock (placeholder)') }}</flux:heading>
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Stock movements, warehouses, and transfers will be added in a later iteration. This route is wired for navigation parity with the logistics ERP checklist.') }}
        </flux:text>
    </flux:card>
</div>
