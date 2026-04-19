<?php

use App\Authorization\LogisticsPermission;
use App\Models\Document;
use App\Models\Employee;
use App\Services\HR\DriverPerformanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Lazy, Title('Employee Details')] class extends Component
{
    public Employee $employee;

    #[Url]
    public string $tab = 'overview';

    // Edit form fields
    public string $editFirstName              = '';
    public string $editLastName               = '';
    public string $editNationalId             = '';
    public string $editBloodGroup             = '';
    public string $editPhone                  = '';
    public string $editEmail                  = '';
    public bool   $editIsDriver               = false;
    public string $editLicenseClass           = '';
    public string $editLicenseValidUntil      = '';
    public string $editSrcValidUntil          = '';
    public string $editPsychotechnicalValidUntil = '';

    public function mount(int $id): void
    {
        $this->employee = Employee::query()
            ->with(['tenant', 'user', 'leaves', 'advances', 'payrolls', 'drivenShipments.order'])
            ->findOrFail($id);

        Gate::authorize('view', $this->employee);

        $e = $this->employee;
        $this->editFirstName               = $e->first_name ?? '';
        $this->editLastName                = $e->last_name ?? '';
        $this->editNationalId              = $e->national_id ?? '';
        $this->editBloodGroup              = $e->blood_group ?? '';
        $this->editPhone                   = $e->phone ?? '';
        $this->editEmail                   = $e->email ?? '';
        $this->editIsDriver                = (bool) $e->is_driver;
        $this->editLicenseClass            = $e->license_class ?? '';
        $this->editLicenseValidUntil       = $e->license_valid_until?->format('Y-m-d') ?? '';
        $this->editSrcValidUntil           = $e->src_valid_until?->format('Y-m-d') ?? '';
        $this->editPsychotechnicalValidUntil = $e->psychotechnical_valid_until?->format('Y-m-d') ?? '';
    }

    public function saveEmployee(): void
    {
        if (! Auth::user()?->can(LogisticsPermission::ADMIN)) {
            session()->flash('error', __('Only admins can edit employee records.'));

            return;
        }

        Gate::authorize('update', $this->employee);

        $validated = $this->validate([
            'editFirstName'    => ['required', 'string', 'max:100'],
            'editLastName'     => ['required', 'string', 'max:100'],
            'editNationalId'   => ['nullable', 'string', 'max:20'],
            'editBloodGroup'   => ['nullable', 'string', 'max:5'],
            'editPhone'        => ['nullable', 'string', 'max:30'],
            'editEmail'        => ['nullable', 'email', 'max:200'],
            'editIsDriver'     => ['boolean'],
            'editLicenseClass' => ['nullable', 'string', 'max:10'],
            'editLicenseValidUntil'          => ['nullable', 'date'],
            'editSrcValidUntil'              => ['nullable', 'date'],
            'editPsychotechnicalValidUntil'  => ['nullable', 'date'],
        ]);

        $this->employee->update([
            'first_name'                  => $validated['editFirstName'],
            'last_name'                   => $validated['editLastName'],
            'national_id'                 => filled($validated['editNationalId']) ? $validated['editNationalId'] : null,
            'blood_group'                 => filled($validated['editBloodGroup']) ? $validated['editBloodGroup'] : null,
            'phone'                       => filled($validated['editPhone']) ? $validated['editPhone'] : null,
            'email'                       => filled($validated['editEmail']) ? $validated['editEmail'] : null,
            'is_driver'                   => $validated['editIsDriver'],
            'license_class'               => filled($validated['editLicenseClass']) ? $validated['editLicenseClass'] : null,
            'license_valid_until'         => filled($validated['editLicenseValidUntil']) ? $validated['editLicenseValidUntil'] : null,
            'src_valid_until'             => filled($validated['editSrcValidUntil']) ? $validated['editSrcValidUntil'] : null,
            'psychotechnical_valid_until' => filled($validated['editPsychotechnicalValidUntil']) ? $validated['editPsychotechnicalValidUntil'] : null,
        ]);

        $this->employee->refresh();
        session()->flash('success', __('Employee record updated.'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    #[Computed]
    public function employeeDocuments(): \Illuminate\Database\Eloquent\Collection
    {
        return Document::query()
            ->where('documentable_type', Employee::class)
            ->where('documentable_id', $this->employee->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /** @return array{totalLeaves:int,pendingLeaves:int,totalAdvances:float,draftPayrolls:int} */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'totalLeaves'   => $this->employee->leaves->count(),
            'pendingLeaves' => $this->employee->leaves->filter(fn ($l) => $l->status->value === 'pending')->count(),
            'totalAdvances' => (float) $this->employee->advances->sum('amount'),
            'draftPayrolls' => $this->employee->payrolls->filter(fn ($p) => $p->status->value === 'draft')->count(),
        ];
    }

    /**
     * Şoför performans skoru — sadece is_driver=true olan çalışanlar için anlımlıdır.
     *
     * @return array{score: int, deliveries_90d: int, expired_docs: int, grade: string}
     */
    #[Computed]
    public function driverPerformance(): array
    {
        return app(DriverPerformanceService::class)->scoreForEmployee($this->employee);
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

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4 {{ $employee->is_driver ? 'lg:grid-cols-5' : '' }}">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total leaves') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['totalLeaves'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Pending leaves') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pendingLeaves'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pendingLeaves'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total advances (TRY)') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['totalAdvances'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Draft payrolls') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['draftPayrolls'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['draftPayrolls'] }}
            </flux:heading>
        </flux:card>
        @if ($employee->is_driver)
            @php
                $perf = $this->driverPerformance;
                $gradeColor = match ($perf['grade']) { 'A' => 'text-green-600', 'B' => 'text-blue-600', 'C' => 'text-yellow-500', default => 'text-red-500' };
            @endphp
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500">{{ __('Driver score') }}</flux:text>
                <div class="flex items-baseline gap-2">
                    <flux:heading size="lg" class="{{ $gradeColor }}">{{ $perf['score'] }}</flux:heading>
                    <span class="text-sm font-bold {{ $gradeColor }}">{{ $perf['grade'] }}</span>
                </div>
                <flux:text class="mt-1 text-xs text-zinc-500">
                    {{ $perf['deliveries_90d'] }} {{ __('deliveries (90d)') }}
                </flux:text>
            </flux:card>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2 border-b border-border pb-2">
        <flux:button type="button" size="sm" :variant="$tab === 'overview' ? 'primary' : 'ghost'" wire:click="$set('tab','overview')">
            {{ __('Overview') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$tab === 'leaves' ? 'primary' : 'ghost'" wire:click="$set('tab','leaves')">
            {{ __('Leaves') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$tab === 'advances' ? 'primary' : 'ghost'" wire:click="$set('tab','advances')">
            {{ __('Advances') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$tab === 'payrolls' ? 'primary' : 'ghost'" wire:click="$set('tab','payrolls')">
            {{ __('Payrolls') }}
        </flux:button>
        @if ($employee->is_driver)
            <flux:button type="button" size="sm" :variant="$tab === 'trips' ? 'primary' : 'ghost'" wire:click="$set('tab','trips')">
                {{ __('Trips') }}
            </flux:button>
        @endif
        <flux:button type="button" size="sm" :variant="$tab === 'documents' ? 'primary' : 'ghost'" wire:click="$set('tab','documents')">
            {{ __('Documents') }}
        </flux:button>
        <flux:button type="button" size="sm" :variant="$tab === 'activity' ? 'primary' : 'ghost'" wire:click="$set('tab','activity')">
            {{ __('Activity log') }}
        </flux:button>
        @can(\App\Authorization\LogisticsPermission::ADMIN)
            <flux:button type="button" size="sm" :variant="$tab === 'edit' ? 'primary' : 'ghost'" wire:click="$set('tab','edit')">
                {{ __('Edit') }}
            </flux:button>
        @endcan
    </div>

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
                            <th class="py-2 pe-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($employee->payrolls->sortByDesc('period_start') as $payroll)
                            <tr>
                                <td class="py-2 pe-3 font-medium">
                                    {{ $payroll->period_start?->format('Y-m') ?? '—' }}
                                </td>
                                <td class="py-2 pe-3 text-end font-mono">{{ number_format((float) $payroll->gross_salary, 2) }}</td>
                                <td class="py-2 pe-3 text-end font-mono text-red-600">
                                    -{{ number_format($payroll->totalDeductions(), 2) }}
                                </td>
                                <td class="py-2 pe-3 text-end font-mono font-bold text-green-600">
                                    {{ number_format((float) $payroll->net_salary, 2) }} {{ $payroll->currency_code }}
                                </td>
                                <td class="py-2 pe-3">
                                    <flux:badge color="{{ $payroll->status->color() }}" size="sm">{{ $payroll->status->label() }}</flux:badge>
                                </td>
                                <td class="py-2 pe-3">
                                    @if ($payroll->status->value !== 'draft')
                                        <a href="{{ route('admin.hr.payroll.print', $payroll) }}" target="_blank"
                                           class="text-xs text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">
                                            {{ __('Print') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-zinc-500">{{ __('No payroll records found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <flux:button variant="outline" icon="arrow-top-right-on-square" :href="route('admin.hr.payroll.index')" wire:navigate>
                    {{ __('Manage payroll') }}
                </flux:button>
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

    {{-- TAB: Edit --}}
    @if ($tab === 'edit')
        @can(\App\Authorization\LogisticsPermission::ADMIN)
            <flux:card class="p-6">
                @if (session()->has('success'))
                    <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('success') }}</flux:callout>
                @endif

                <form wire:submit.prevent="saveEmployee" class="flex flex-col gap-6 max-w-2xl">

                    {{-- Personal Information --}}
                    <div>
                        <flux:heading size="sm" class="mb-4">{{ __('Personal Information') }}</flux:heading>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('First name') }} <span class="text-red-500">*</span></flux:label>
                                <flux:input wire:model="editFirstName" />
                                <flux:error name="editFirstName" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Last name') }} <span class="text-red-500">*</span></flux:label>
                                <flux:input wire:model="editLastName" />
                                <flux:error name="editLastName" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('National ID') }}</flux:label>
                                <flux:input wire:model="editNationalId" />
                                <flux:error name="editNationalId" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Blood group') }}</flux:label>
                                <flux:input wire:model="editBloodGroup" placeholder="A+, B-, O+, AB+…" />
                                <flux:error name="editBloodGroup" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Phone') }}</flux:label>
                                <flux:input wire:model="editPhone" type="tel" />
                                <flux:error name="editPhone" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Email') }}</flux:label>
                                <flux:input wire:model="editEmail" type="email" />
                                <flux:error name="editEmail" />
                            </flux:field>
                        </div>
                    </div>

                    <flux:separator />

                    {{-- Driver Credentials --}}
                    <div>
                        <flux:heading size="sm" class="mb-4">{{ __('Driver Credentials') }}</flux:heading>
                        <div class="mb-4">
                            <flux:field>
                                <div class="flex items-center gap-3">
                                    <flux:checkbox wire:model.live="editIsDriver" id="edit_is_driver" />
                                    <flux:label for="edit_is_driver">{{ __('This employee is a driver') }}</flux:label>
                                </div>
                            </flux:field>
                        </div>
                        @if ($editIsDriver)
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>{{ __('License class') }}</flux:label>
                                    <flux:input wire:model="editLicenseClass" placeholder="C, CE, D…" />
                                    <flux:error name="editLicenseClass" />
                                </flux:field>
                                <div></div>
                                <flux:field>
                                    <flux:label>{{ __('License valid until') }}</flux:label>
                                    <flux:input wire:model="editLicenseValidUntil" type="date" />
                                    <flux:error name="editLicenseValidUntil" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>{{ __('SRC valid until') }}</flux:label>
                                    <flux:input wire:model="editSrcValidUntil" type="date" />
                                    <flux:error name="editSrcValidUntil" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>{{ __('Psychotechnical valid until') }}</flux:label>
                                    <flux:input wire:model="editPsychotechnicalValidUntil" type="date" />
                                    <flux:error name="editPsychotechnicalValidUntil" />
                                </flux:field>
                            </div>
                        @endif
                    </div>

                    <div>
                        <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:card>
        @endcan
    @endif

    {{-- TAB: Documents --}}
    @if ($tab === 'documents')
        <flux:card class="p-4">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                <flux:button :href="route('admin.documents.index')" size="sm" variant="ghost" wire:navigate>
                    {{ __('Manage all') }}
                </flux:button>
            </div>
            @if ($this->employeeDocuments->isEmpty())
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No documents for this employee yet.') }}</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-start text-zinc-500 dark:text-zinc-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Title') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Category') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('File type') }}</th>
                                <th class="py-2 font-medium">{{ __('Expires at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->employeeDocuments as $doc)
                                @php $expired = $doc->expires_at && $doc->expires_at->isPast(); @endphp
                                <tr class="{{ $expired ? 'bg-red-50 dark:bg-red-950/20' : '' }}">
                                    <td class="py-2 pe-4 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $doc->title }}
                                        @if ($expired)
                                            <flux:badge color="red" size="sm" class="ms-1">{{ __('Expired') }}</flux:badge>
                                        @elseif ($doc->expires_at && $doc->expires_at->diffInDays() <= 30)
                                            <flux:badge color="yellow" size="sm" class="ms-1">{{ __('Expiring soon') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4">
                                        @if ($doc->category)
                                            <flux:badge color="{{ $doc->category->color() }}" size="sm">{{ $doc->category->label() }}</flux:badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4 font-mono text-xs text-zinc-500">{{ $doc->file_type?->value ?? '—' }}</td>
                                    <td class="py-2 {{ $expired ? 'font-semibold text-red-600' : 'text-zinc-500' }}">
                                        {{ $doc->expires_at?->format('d M Y') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @endif

    {{-- TAB: Trips (drivers only) --}}
    @if ($tab === 'trips' && $employee->is_driver)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('Driven trips') }}</flux:heading>
            @if ($employee->drivenShipments->isEmpty())
                <flux:text class="text-sm text-zinc-500">{{ __('No shipments driven yet.') }}</flux:text>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-start text-zinc-500 dark:text-zinc-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Shipment') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Order') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Status') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Dispatched') }}</th>
                                <th class="py-2 pe-4 font-medium">{{ __('Delivered') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($employee->drivenShipments->sortByDesc('created_at') as $shipment)
                                <tr>
                                    <td class="py-2 pe-4">
                                        <flux:link :href="route('admin.shipments.show', $shipment)" wire:navigate class="font-medium">
                                            #{{ $shipment->id }}
                                        </flux:link>
                                    </td>
                                    <td class="py-2 pe-4 text-zinc-500">{{ $shipment->order?->order_number ?? '—' }}</td>
                                    <td class="py-2 pe-4">
                                        @php
                                            $statusColor = match ($shipment->status) {
                                                \App\Enums\ShipmentStatus::Planned    => 'blue',
                                                \App\Enums\ShipmentStatus::Dispatched => 'amber',
                                                \App\Enums\ShipmentStatus::Delivered  => 'green',
                                                \App\Enums\ShipmentStatus::Cancelled  => 'red',
                                                default => 'zinc',
                                            };
                                            $statusLabel = match ($shipment->status) {
                                                \App\Enums\ShipmentStatus::Planned    => __('Planned'),
                                                \App\Enums\ShipmentStatus::Dispatched => __('Dispatched'),
                                                \App\Enums\ShipmentStatus::Delivered  => __('Delivered'),
                                                \App\Enums\ShipmentStatus::Cancelled  => __('Cancelled'),
                                                default => (string) $shipment->status,
                                            };
                                        @endphp
                                        <flux:badge color="{{ $statusColor }}" size="sm">{{ $statusLabel }}</flux:badge>
                                    </td>
                                    <td class="py-2 pe-4 text-zinc-500">{{ $shipment->dispatched_at?->format('d M Y') ?? '—' }}</td>
                                    <td class="py-2 pe-4 text-zinc-500">{{ $shipment->delivered_at?->format('d M Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>
    @endif
</div>
