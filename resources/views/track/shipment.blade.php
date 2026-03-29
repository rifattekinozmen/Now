<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Shipment status') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-zinc-50 p-6 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        @php
            $statusLabel = match ($shipment->status) {
                \App\Enums\ShipmentStatus::Planned => __('Planned'),
                \App\Enums\ShipmentStatus::Dispatched => __('Dispatched'),
                \App\Enums\ShipmentStatus::Delivered => __('Delivered'),
                \App\Enums\ShipmentStatus::Cancelled => __('Cancelled'),
            };
        @endphp
        <div class="mx-auto max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <h1 class="text-lg font-semibold">{{ __('Shipment status') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Read-only public view') }}</p>
            <dl class="mt-6 space-y-3 text-sm">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Order') }}</dt>
                    <dd class="font-medium">{{ $shipment->order?->order_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                    <dd class="font-medium">{{ $statusLabel }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Vehicle plate') }}</dt>
                    <dd class="font-medium">{{ $shipment->vehicle?->plate ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </body>
</html>
