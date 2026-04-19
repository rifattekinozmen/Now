<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('New order request')] class extends Component
{
    public string $sasNo         = '';
    public string $dueDate       = '';
    public string $incoterms     = '';
    public string $tonnage       = '';
    public string $loadingSite   = '';
    public string $unloadingSite = '';
    public string $memo          = '';

    public function mount(): void
    {
        if (! auth()->user()?->customer_id) {
            abort(403);
        }
    }

    public function submitOrder(): void
    {
        $user = auth()->user();

        if (! $user?->customer_id) {
            abort(403);
        }

        $validated = $this->validate([
            'sasNo'         => ['nullable', 'string', 'max:64'],
            'dueDate'       => ['nullable', 'date'],
            'incoterms'     => ['nullable', 'string', 'max:32'],
            'tonnage'       => ['nullable', 'numeric', 'min:0.1', 'max:9999'],
            'loadingSite'   => ['required', 'string', 'max:1000'],
            'unloadingSite' => ['required', 'string', 'max:1000'],
            'memo'          => ['nullable', 'string', 'max:2000'],
        ]);

        $orderNumber = $this->uniqueOrderNumber();

        $order = Order::query()->create([
            'tenant_id'      => $user->tenant_id,
            'customer_id'    => $user->customer_id,
            'order_number'   => $orderNumber,
            'status'         => OrderStatus::Draft,
            'ordered_at'     => now(),
            'sas_no'         => $validated['sasNo'] ?: null,
            'due_date'       => $validated['dueDate'] ?: null,
            'incoterms'      => $validated['incoterms'] ?: null,
            'tonnage'        => filled($validated['tonnage']) ? $validated['tonnage'] : null,
            'loading_site'   => $validated['loadingSite'],
            'unloading_site' => $validated['unloadingSite'],
            'meta'           => $validated['memo'] ? ['customer_note' => $validated['memo']] : null,
        ]);

        $this->redirect(route('customer.orders.show', $order), navigate: true);
    }

    private function uniqueOrderNumber(): string
    {
        do {
            $number = 'CR-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (Order::query()->withoutGlobalScopes()->where('order_number', $number)->exists());

        return $number;
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('New order request') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                {{ __('Fill in the details below. Our team will review and confirm your order shortly.') }}
            </flux:text>
        </div>
        <flux:button :href="route('customer.orders.index')" variant="ghost" size="sm" wire:navigate>
            ← {{ __('My orders') }}
        </flux:button>
    </div>

    <flux:card class="p-6">
        <form wire:submit="submitOrder" class="flex flex-col gap-5">

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('SAS / PO reference') }}</flux:label>
                    <flux:input wire:model="sasNo" placeholder="PO-2026-001" />
                    <flux:error name="sasNo" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Requested delivery date') }}</flux:label>
                    <flux:input wire:model="dueDate" type="date" />
                    <flux:error name="dueDate" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Incoterms') }}</flux:label>
                    <flux:input wire:model="incoterms" placeholder="EXW, FCA, DAP…" />
                    <flux:error name="incoterms" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Tonnage') }}</flux:label>
                    <flux:input wire:model="tonnage" type="number" step="0.01" min="0" placeholder="0.00" />
                    <flux:error name="tonnage" />
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Loading site') }} *</flux:label>
                    <flux:textarea wire:model="loadingSite" rows="2" :placeholder="__('Address or location of loading point')" required />
                    <flux:error name="loadingSite" />
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Unloading site') }} *</flux:label>
                    <flux:textarea wire:model="unloadingSite" rows="2" :placeholder="__('Address or location of delivery point')" required />
                    <flux:error name="unloadingSite" />
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Notes') }}</flux:label>
                    <flux:textarea wire:model="memo" rows="3" :placeholder="__('Any special instructions or requirements…')" />
                    <flux:error name="memo" />
                </flux:field>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled">
                    {{ __('Submit order request') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="ghost"
                    :href="route('customer.orders.index')"
                    wire:navigate
                >
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
