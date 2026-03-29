<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Proof of delivery') }} — {{ __('Shipment') }} #{{ $shipment->id }}</title>
        @vite(['resources/css/app.css'])
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }
                body {
                    background: white !important;
                }
            }
        </style>
    </head>
    <body class="bg-zinc-50 p-6 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        @php
            $pod = is_array($shipment->pod_payload) ? $shipment->pod_payload : [];
        @endphp
        <div class="mx-auto max-w-2xl rounded-xl border border-zinc-200 bg-white p-8 dark:border-zinc-700 dark:bg-zinc-800">
            <h1 class="text-xl font-semibold">{{ __('Proof of delivery') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Shipment') }} #{{ $shipment->id }}
                @if ($shipment->order?->order_number)
                    — {{ __('Order') }} {{ $shipment->order->order_number }}
                @endif
            </p>

            <dl class="mt-8 space-y-4 text-sm">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Received by') }}</dt>
                    <dd class="font-medium">{{ $pod['received_by'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Note') }}</dt>
                    <dd class="whitespace-pre-wrap font-medium">{{ $pod['note'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Delivered at') }}</dt>
                    <dd class="font-medium">
                        {{ $shipment->delivered_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    </dd>
                </div>
                @if (! empty($pod['signed_at']))
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Signed at') }}</dt>
                        <dd class="font-medium">{{ $pod['signed_at'] }}</dd>
                    </div>
                @endif
            </dl>

            @if ($hasSignature)
                <div class="mt-8">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Signature') }}</p>
                    <img
                        src="{{ route('admin.shipments.pod.signature', $shipment) }}"
                        alt="{{ __('Signature') }}"
                        class="mt-2 max-h-48 max-w-full border border-zinc-200 dark:border-zinc-600"
                    />
                </div>
            @endif

            <p class="no-print mt-10 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Use your browser print dialog to save as PDF.') }}
            </p>
            <p class="no-print mt-4">
                <a href="{{ route('admin.shipments.show', $shipment) }}" class="text-primary underline">{{ __('Back to shipment') }}</a>
            </p>
        </div>
    </body>
</html>
