<x-mail::message>
# {{ __('Your payroll has been approved') }}

{{ __('Hello') }}, {{ $payroll->employee?->fullName() ?? __('Employee') }},

{{ __('Your payroll for the period :start – :end has been approved.', [
    'start' => $payroll->period_start?->format('d M Y'),
    'end'   => $payroll->period_end?->format('d M Y'),
]) }}

<x-mail::table>
| | |
|---|---|
| **{{ __('Gross salary') }}** | {{ number_format((float) $payroll->gross_salary, 2) }} {{ $payroll->currency_code }} |
| **{{ __('Net salary') }}** | {{ number_format((float) $payroll->net_salary, 2) }} {{ $payroll->currency_code }} |
| **{{ __('Approved at') }}** | {{ $payroll->approved_at?->format('d M Y H:i') }} |
</x-mail::table>

{{ __('Please contact HR if you have any questions.') }}

{{ config('app.name') }}
</x-mail::message>
