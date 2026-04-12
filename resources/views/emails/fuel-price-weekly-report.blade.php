<x-mail::message>
# {{ __('Weekly fuel price report') }}

**{{ $weekLabel }}**

{{ __('Here is the weekly fuel price summary for your fleet operations:') }}

<x-mail::table>
| {{ __('Metric') }} | {{ __('Value') }} |
|---|---|
| **{{ __('Average price (₺/L)') }}** | {{ number_format((float) ($summary['avg_price'] ?? 0), 3) }} |
| **{{ __('Min price (₺/L)') }}** | {{ number_format((float) ($summary['min_price'] ?? 0), 3) }} |
| **{{ __('Max price (₺/L)') }}** | {{ number_format((float) ($summary['max_price'] ?? 0), 3) }} |
| **{{ __('Total intake (L)') }}** | {{ number_format((float) ($summary['total_liters'] ?? 0), 0) }} |
| **{{ __('Total cost (₺)') }}** | {{ number_format((float) ($summary['total_cost'] ?? 0), 2) }} |
| **{{ __('Anomalies detected') }}** | {{ $summary['anomaly_count'] ?? 0 }} |
</x-mail::table>

@if (!empty($summary['anomalies']))
<x-mail::panel>
**{{ __('Fuel anomalies requiring attention:') }}**

@foreach ($summary['anomalies'] as $anomaly)
- {{ $anomaly['plate'] ?? '?' }} — {{ $anomaly['message'] ?? '' }}
@endforeach
</x-mail::panel>
@endif

{{ config('app.name') }}
</x-mail::message>
