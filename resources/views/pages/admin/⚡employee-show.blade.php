<?php

use App\Models\Employee;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Employee Details')] class extends Component
{
    public Employee $employee;

    #[Url]
    public string $tab = 'overview';

    public function mount(int $id): void
    {
        $this->employee = Employee::query()
            ->with(['tenant', 'user', 'leaves', 'advances', 'payrolls'])
            ->findOrFail($id);

        Gate::authorize('view', $this->employee);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <x-avatar :name="$employee->fullName()" />
            <div>
                <flux:heading size="xl">
                    {{ $employee->fullName() }}
                    @if ($employee->is_driver)
                        <flux:badge color="blue" size="sm" class="ms-2">{{ __('Driver') }}</flux:badge>
                    @endif
                </flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    {{ $employee->email }}
                    @if ($employee->phone)
                        · {{ $employee->phone }}
                    @endif
                </flux:text>
            </div>
        </div>
        <flux:button variant="ghost" icon="arrow-left" :href="route('admin.employees.index')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    {{-- Tabs --}}
    <flux:tabs wire:model="tab">
        <flux:tab name="overview" icon="information-circle">{{ __('Overview') }}</flux:tab>
        <flux:tab name="leaves" icon="calendar-days">{{ __('Leaves') }}</flux:tab>
        <flux:tab name="advances" icon="banknotes">{{ __('Advances') }}</flux:tab>
        <flux:tab name="payrolls" icon="document-text">{{ __('Payrolls') }}</flux:tab>
        <flux:tab name="activity" icon="clock">{{ __('Activity log') }}</flux:tab>
    </flux:tabs>

    {{-- TAB: Overview --}}
    @if ($tab === 'overview')
        <div class="grid gap-6 md:grid-cols-2">
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Personal Information') }}</flux:heading>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('First name') }}</flux:text>
                        <flux:text class="font-medium">{{ $employee->first_name }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Last name') }}</flux:text>
                        <flux:text class="font-medium">{{ $employee->last_name }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('National ID') }}</flux:text>
                        <flux:text class="font-medium">{{ $employee->national_id ?? '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Blood group') }}</flux:text>
                        <flux:text class="font-medium">{{ $employee->blood_group ?? '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Phone') }}</flux:text>
                        <flux:text class="font-medium">{{ $employee->phone ?? '—' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500 uppercase tracking-wider">{{ __('Email') }}</flux:text>
                        <flux:text class="font-medium">{{ $employee->email ?? '—' }}</flux:text>
                    </div>
                </dl>
            </flux:card>

            @if ($employee->is_driver)
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4 px-2">{{ __('Driver Credentials') }}</flux:heading>
                    
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Credential') }}</flux:table.column>
                            <flux:table.column>{{ __('Value') }}</flux:table.column>
                            <flux:table.column>{{ __('Valid Until') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ __('License Class') }}</flux:table.cell>
                                <flux:table.cell>{{ $employee->license_class ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $employee->license_valid_until?->format('d M Y') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($employee->license_valid_until?->isPast())
                                        <flux:badge color="red" size="sm">{{ __('Expired') }}</flux:badge>
                                    @elseif ($employee->license_valid_until?->diffInDays() < 30)
                                        <flux:badge color="yellow" size="sm">{{ __('Expiring soon') }}</flux:badge>
                                    @else
                                        <flux:badge color="green" size="sm">{{ __('Valid') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>

                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ __('SRC Document') }}</flux:table.cell>
                                <flux:table.cell>—</flux:table.cell>
                                <flux:table.cell>{{ $employee->src_valid_until?->format('d M Y') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($employee->src_valid_until?->isPast())
                                        <flux:badge color="red" size="sm">{{ __('Expired') }}</flux:badge>
                                    @elseif ($employee->src_valid_until?->diffInDays() < 30)
                                        <flux:badge color="yellow" size="sm">{{ __('Expiring soon') }}</flux:badge>
                                    @else
                                        <flux:badge color="green" size="sm">{{ __('Valid') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>

                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ __('Psychotechnical') }}</flux:table.cell>
                                <flux:table.cell>—</flux:table.cell>
                                <flux:table.cell>{{ $employee->psychotechnical_valid_until?->format('d M Y') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($employee->psychotechnical_valid_until?->isPast())
                                        <flux:badge color="red" size="sm">{{ __('Expired') }}</flux:badge>
                                    @elseif ($employee->psychotechnical_valid_until?->diffInDays() < 30)
                                        <flux:badge color="yellow" size="sm">{{ __('Expiring soon') }}</flux:badge>
                                    @else
                                        <flux:badge color="green" size="sm">{{ __('Valid') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            @endif
        </div>
    @endif

    {{-- TAB: Leaves --}}
    @if ($tab === 'leaves')
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('ID') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Type') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Dates') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Days') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($employee->leaves->sortByDesc('start_date') as $leave)
                            <tr>
                                <td class="py-2 pe-3 font-mono">#{{ $leave->id }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $leave->type->color() }}" size="sm">{{ $leave->type->label() }}</flux:badge>
                                </td>
                                <td class="py-2 pe-3">
                                    {{ $leave->start_date?->format('d M Y') }} - {{ $leave->end_date?->format('d M Y') }}
                                </td>
                                <td class="py-2 pe-3 font-mono">{{ $leave->days_count }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $leave->status->color() }}" size="sm">{{ $leave->status->label() }}</flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-zinc-500">{{ __('No leave records found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    {{-- TAB: Advances --}}
    @if ($tab === 'advances')
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('ID') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Amount') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Requested At') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Repayment') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($employee->advances->sortByDesc('requested_at') as $advance)
                            <tr>
                                <td class="py-2 pe-3 font-mono">#{{ $advance->id }}</td>
                                <td class="py-2 pe-3 font-mono font-medium">
                                    {{ number_format((float) $advance->amount, 2) }} {{ $advance->currency_code }}
                                </td>
                                <td class="py-2 pe-3">{{ $advance->requested_at?->format('d M Y') }}</td>
                                <td class="py-2 pe-3 text-zinc-500">{{ $advance->repayment_date?->format('d M Y') ?? '—' }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $advance->status->color() }}" size="sm">{{ $advance->status->label() }}</flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-zinc-500">{{ __('No advance records found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    {{-- TAB: Payrolls --}}
    @if ($tab === 'payrolls')
        <flux:card class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Period') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Gross') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Deductions') }}</th>
                            <th class="py-2 pe-3 font-medium text-end">{{ __('Net Pay') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($employee->payrolls->sortByDesc('period_month')->sortByDesc('period_year') as $payroll)
                            <tr>
                                <td class="py-2 pe-3 font-medium">
                                    {{ $payroll->period_year }}-{{ str_pad((string)$payroll->period_month, 2, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="py-2 pe-3 text-end font-mono">{{ number_format((float) $payroll->base_gross_salary, 2) }}</td>
                                <td class="py-2 pe-3 text-end font-mono text-red-600">
                                    -{{ number_format((float) $payroll->total_deductions, 2) }}
                                </td>
                                <td class="py-2 pe-3 text-end font-mono font-bold text-green-600">
                                    {{ number_format((float) $payroll->net_salary, 2) }} {{ $payroll->currency_code }}
                                </td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $payroll->status->color() }}" size="sm">{{ $payroll->status->label() }}</flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-zinc-500">{{ __('No payroll records found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    {{-- TAB: Activity Log --}}
    @if ($tab === 'activity')
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('Activity log') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr class="text-start text-zinc-500 dark:text-zinc-400">
                            <th class="py-2 pe-3 font-medium">{{ __('Date') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Event') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('User') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($employee->activityLogs()->with('user')->take(20)->get() as $log)
                            <tr>
                                <td class="py-2 pe-3 whitespace-nowrap text-xs text-zinc-500">{{ $log->created_at?->format('d M Y H:i') }}</td>
                                <td class="py-2 pe-3">
                                    <flux:badge size="sm" color="{{ match($log->event) { 'created' => 'green', 'deleted' => 'red', default => 'blue' } }}">
                                        {{ $log->event }}
                                    </flux:badge>
                                </td>
                                <td class="py-2 pe-3">{{ $log->user?->name ?? __('System') }}</td>
                                <td class="py-2 pe-3 text-xs text-zinc-500">{{ $log->description ?? (isset($log->properties['changed']) ? implode(', ', $log->properties['changed']) : '—') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-zinc-500">{{ __('No activity recorded yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
</div>
