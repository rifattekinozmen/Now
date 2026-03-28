<?php

use App\Models\Customer;
use App\Models\DeliveryNumber;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Services\Logistics\TcmbExchangeRateService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    public function refreshTcmb(TcmbExchangeRateService $tcmb): void
    {
        if ($tcmb->tryRefreshFromRemote()) {
            session()->flash('status', __('TCMB rates cached successfully.'));
        } else {
            session()->flash('error', __('Could not fetch TCMB rates. Try again later.'));
        }

        unset($this->tcmbSnapshot);
    }

    /**
     * @return array{rates: array<string, string>, at: ?string}
     */
    #[Computed]
    public function tcmbSnapshot(): array
    {
        $svc = app(TcmbExchangeRateService::class);

        return [
            'rates' => $svc->storedRates(),
            'at' => $svc->storedFetchedAt(),
        ];
    }

    public function countCustomers(): int
    {
        return Customer::query()->count();
    }

    public function countVehicles(): int
    {
        return Vehicle::query()->count();
    }

    public function countOrders(): int
    {
        return Order::query()->count();
    }

    public function countOpenShipments(): int
    {
        return Shipment::query()->whereNotIn('status', [\App\Enums\ShipmentStatus::Delivered, \App\Enums\ShipmentStatus::Cancelled])->count();
    }

    public function countAvailablePins(): int
    {
        return DeliveryNumber::query()
            ->where('status', \App\Enums\DeliveryNumberStatus::Available)
            ->count();
    }
}; ?>

<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="xl">{{ __('Operations overview') }}</flux:heading>
            <flux:button type="button" wire:click="refreshTcmb" variant="ghost" size="sm">
                {{ __('Refresh TCMB rates') }}
            </flux:button>
        </div>

        @if (session()->has('status'))
            <flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>
        @endif
        @if (session()->has('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Cached FX (TCMB ForexBuying → TRY per 1 unit)') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Not financial advice. For operational reference only.') }}
                @if ($this->tcmbSnapshot['at'])
                    — {{ __('Updated:') }} {{ $this->tcmbSnapshot['at'] }}
                @endif
            </flux:text>
            <div class="flex flex-wrap gap-4 text-sm">
                @foreach (['USD', 'EUR', 'GBP'] as $ccy)
                    <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-600">
                        <span class="font-medium">{{ $ccy }}</span>
                        <span class="ms-2 text-zinc-600 dark:text-zinc-400">{{ $this->tcmbSnapshot['rates'][$ccy] ?? '—' }}</span>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Customers') }}</flux:text>
                <flux:heading size="xl">{{ $this->countCustomers() }}</flux:heading>
            </flux:card>
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Vehicles') }}</flux:text>
                <flux:heading size="xl">{{ $this->countVehicles() }}</flux:heading>
            </flux:card>
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Orders') }}</flux:text>
                <flux:heading size="xl">{{ $this->countOrders() }}</flux:heading>
            </flux:card>
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Open shipments') }}</flux:text>
                <flux:heading size="xl">{{ $this->countOpenShipments() }}</flux:heading>
            </flux:card>
            <flux:card class="flex flex-col gap-1">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('PINs available') }}</flux:text>
                <flux:heading size="xl">{{ $this->countAvailablePins() }}</flux:heading>
            </flux:card>
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button :href="route('admin.orders.index')" variant="primary" wire:navigate>{{ __('Orders') }}</flux:button>
            <flux:button :href="route('admin.shipments.index')" variant="filled" wire:navigate>{{ __('Shipments') }}</flux:button>
            <flux:button :href="route('admin.delivery-numbers.index')" variant="filled" wire:navigate>{{ __('PIN pool') }}</flux:button>
        </div>
    </div>
</x-layouts::app>
