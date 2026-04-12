<x-mail::message>
# {{ __('Payment due reminder') }}

{{ __('Hello') }}, {{ $recipientName }},

{{ __('The following orders have upcoming or overdue payment deadlines:') }}

<x-mail::table>
| {{ __('Order') }} | {{ __('Customer') }} | {{ __('Due date') }} | {{ __('Amount') }} |
|---|---|---|---|
@foreach ($orders as $order)
| {{ $order->reference_no ?? '#'.$order->id }} | {{ $order->customer?->name ?? '—' }} | {{ $order->due_date?->format('d M Y') ?? '—' }} | {{ number_format((float) ($order->freight_amount ?? 0), 2) }} {{ $order->currency_code }} |
@endforeach
</x-mail::table>

{{ __('Please follow up on these payments as soon as possible.') }}

{{ config('app.name') }}
</x-mail::message>
