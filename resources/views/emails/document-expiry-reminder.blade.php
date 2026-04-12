<x-mail::message>
# {{ __('Document expiry reminder') }}

@php
    $entityLabels = [
        'vehicle_inspection'        => __('Vehicle inspection'),
        'employee_license'          => __('Driver license'),
        'employee_src'              => __('SRC certificate'),
        'employee_psychotechnical'  => __('Psychotechnical certificate'),
    ];
    $entityLabel = $entityLabels[$context['entity']] ?? $context['entity'];
@endphp

**{{ $entityLabel }}** {{ __('will expire in :days day(s).', ['days' => $context['days_remaining']]) }}

<x-mail::table>
| | |
|---|---|
@if (!empty($context['plate']))
| **{{ __('Vehicle') }}** | {{ $context['plate'] }} |
@endif
@if (!empty($context['name']))
| **{{ __('Employee') }}** | {{ $context['name'] }} |
@endif
| **{{ __('Expires on') }}** | {{ $context['expires_on'] }} |
| **{{ __('Days remaining') }}** | {{ $context['days_remaining'] }} |
</x-mail::table>

{{ __('Please take action to renew this document before it expires.') }}

{{ config('app.name') }}
</x-mail::message>
