<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('Payroll Slip') }} — {{ $payroll->employee?->fullName() }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #fff;
            padding: 32px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #3b4fd8;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .company-name { font-size: 22px; font-weight: 700; color: #3b4fd8; }
        .doc-title { font-size: 16px; font-weight: 600; color: #444; margin-top: 4px; }
        .meta { text-align: right; font-size: 12px; color: #666; }
        .section { margin-bottom: 20px; }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #3b4fd8;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        .row { display: flex; margin-bottom: 6px; }
        .label { width: 200px; color: #6b7280; font-size: 12px; }
        .value { flex: 1; font-weight: 500; }
        table.salary { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.salary th { background: #f3f4f6; text-align: left; padding: 8px 12px; font-size: 12px; font-weight: 600; border: 1px solid #e5e7eb; }
        table.salary td { padding: 8px 12px; border: 1px solid #e5e7eb; font-size: 13px; }
        .amount { text-align: right; font-family: 'Courier New', monospace; }
        .total-row td { background: #eff6ff; font-weight: 700; font-size: 14px; }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
            font-size: 11px;
            color: #9ca3af;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            border-top: 1px solid #374151;
            width: 180px;
            margin-top: 8px;
            padding-top: 4px;
            font-size: 11px;
            color: #6b7280;
        }
        @media print {
            body { padding: 16px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    {{-- Print button (hidden when printing) --}}
    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()"
            style="background:#3b4fd8;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:13px;">
            🖨️ {{ __('Print / Save PDF') }}
        </button>
        <a href="{{ url()->previous() }}"
            style="margin-left:12px;color:#3b4fd8;text-decoration:none;font-size:13px;">
            ← {{ __('Back') }}
        </a>
    </div>

    <div class="header">
        <div>
            <div class="company-name">{{ $companyName }}</div>
            <div class="doc-title">{{ __('Payroll Slip') }}</div>
            @if ($companyTaxId)
                <div style="font-size:11px;color:#6b7280;margin-top:2px;">{{ __('Tax ID') }}: {{ $companyTaxId }}</div>
            @endif
            @if ($companyAddress || $companyCity)
                <div style="font-size:11px;color:#6b7280;">{{ implode(', ', array_filter([$companyAddress, $companyCity])) }}</div>
            @endif
        </div>
        <div class="meta">
            <div>{{ __('Generated') }}: {{ now()->format('d M Y H:i') }}</div>
            <div>{{ __('Period') }}: {{ $payroll->period_start?->format('d M Y') }} – {{ $payroll->period_end?->format('d M Y') }}</div>
            <div>{{ __('Status') }}: {{ $payroll->status->label() }}</div>
        </div>
    </div>

    {{-- Employee Info --}}
    <div class="section">
        <div class="section-title">{{ __('Employee') }}</div>
        <div class="row">
            <div class="label">{{ __('Name') }}</div>
            <div class="value">{{ $payroll->employee?->fullName() ?? '—' }}</div>
        </div>
        <div class="row">
            <div class="label">{{ __('National ID') }}</div>
            <div class="value">{{ $payroll->employee?->national_id ?? '—' }}</div>
        </div>
        @if ($payroll->employee?->is_driver)
        <div class="row">
            <div class="label">{{ __('License class') }}</div>
            <div class="value">{{ $payroll->employee?->license_class ?? '—' }}</div>
        </div>
        @endif
    </div>

    {{-- Salary Details --}}
    <div class="section">
        <div class="section-title">{{ __('Salary') }}</div>
        <table class="salary">
            <thead>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th class="amount">{{ __('Amount') }} ({{ $payroll->currency_code }})</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ __('Gross salary') }}</td>
                    <td class="amount">{{ number_format((float) $payroll->gross_salary, 2) }}</td>
                </tr>
                @if (is_array($payroll->deductions) && count($payroll->deductions) > 0)
                    @foreach ($payroll->deductions as $deduction)
                        <tr>
                            <td style="color:#dc2626;">{{ $deduction['label'] ?? __('Deduction') }}</td>
                            <td class="amount" style="color:#dc2626;">− {{ number_format((float) ($deduction['amount'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                @endif
                <tr class="total-row">
                    <td>{{ __('Net salary') }}</td>
                    <td class="amount">{{ number_format((float) $payroll->net_salary, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Approval Info --}}
    <div class="section">
        <div class="section-title">{{ __('Approval') }}</div>
        <div class="row">
            <div class="label">{{ __('Approved by') }}</div>
            <div class="value">{{ $payroll->approvedBy?->name ?? '—' }}</div>
        </div>
        <div class="row">
            <div class="label">{{ __('Approved at') }}</div>
            <div class="value">{{ $payroll->approved_at?->format('d M Y H:i') ?? '—' }}</div>
        </div>
        @if ($payroll->paid_at)
        <div class="row">
            <div class="label">{{ __('Paid at') }}</div>
            <div class="value">{{ $payroll->paid_at->format('d M Y H:i') }}</div>
        </div>
        @endif
    </div>

    <div class="footer">
        <div>
            <div>{{ __('Employee signature') }}</div>
            <div class="signature-box">{{ $payroll->employee?->fullName() }}</div>
        </div>
        <div style="text-align:right;">
            <div>{{ __('Authorized signature') }}</div>
            <div class="signature-box">{{ $companyName }}</div>
        </div>
    </div>

</body>
</html>
