<?php

use App\Authorization\LogisticsPermission;
use App\Enums\PayrollStatus;
use App\Mail\PayrollApprovedMail;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Payroll')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form fields
    public string $employee_id    = '';
    public string $period_start   = '';
    public string $period_end     = '';
    public string $gross_salary   = '';
    public string $currency_code  = 'TRY';

    // Bulk generate
    public bool $showBulkGenerate   = false;
    public string $bulkPeriodMonth  = '';
    public string $bulkGrossSalary  = '';
    public string $bulkCurrencyCode = 'TRY';

    // Filters
    public string $filterEmployee  = '';
    public string $filterStatus    = '';
    public string $filterPeriod    = '';

    public bool $filtersOpen = false;

    public string $sortColumn = 'period_start';
    public string $sortDirection = 'desc';

    public ?int $confirmingId = null;
    public string $confirmingAction = '';

    /** @var int[] */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Payroll::class);
        $this->period_start    = now()->startOfMonth()->format('Y-m-d');
        $this->period_end      = now()->endOfMonth()->format('Y-m-d');
        $this->bulkPeriodMonth = now()->format('Y-m');
    }

    public function updatedFilterEmployee(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterPeriod(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'period_start', 'gross_salary', 'net_salary', 'status'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function confirmAction(int $id, string $action): void
    {
        $this->confirmingId = $id;
        $this->confirmingAction = $action;
        $this->modal('confirm-action')->show();
    }

    public function executeAction(): void
    {
        if (! $this->confirmingId) {
            return;
        }
        match ($this->confirmingAction) {
            'approve' => $this->approve($this->confirmingId),
            'paid'    => $this->markPaid($this->confirmingId),
            'delete'  => $this->delete($this->confirmingId),
            default   => null,
        };
        $this->confirmingId = null;
        $this->confirmingAction = '';
    }

    /**
     * @return array{draft:int, approved:int, paid_this_month:float, gross_this_month:float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $thisMonth = now()->format('Y-m');

        return [
            'draft'           => Payroll::query()->draft()->count(),
            'approved'        => Payroll::query()->where('status', PayrollStatus::Approved->value)->count(),
            'paid_this_month' => (float) Payroll::query()
                ->paid()
                ->whereRaw("DATE_FORMAT(period_start, '%Y-%m') = ?", [$thisMonth])
                ->sum('net_salary'),
            'gross_this_month'=> (float) Payroll::query()
                ->whereRaw("DATE_FORMAT(period_start, '%Y-%m') = ?", [$thisMonth])
                ->sum('gross_salary'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Employee>
     */
    #[Computed]
    public function employees(): \Illuminate\Database\Eloquent\Collection
    {
        return Employee::query()->orderBy('first_name')->get();
    }

    private function payrollQuery(): Builder
    {
        $q = Payroll::query()->with(['employee', 'approvedBy']);

        if ($this->filterEmployee !== '') {
            $q->where('employee_id', (int) $this->filterEmployee);
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterPeriod !== '') {
            $q->whereRaw("DATE_FORMAT(period_start, '%Y-%m') = ?", [$this->filterPeriod]);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedPayrolls(): LengthAwarePaginator
    {
        return $this->payrollQuery()->paginate(20);
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedPayrolls->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedPayrolls->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('viewAny', Payroll::class);
        $count = Payroll::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Payroll::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $user = auth()->user();
        if (! ($user instanceof \App\Models\User) || ! LogisticsPermission::canWrite($user, LogisticsPermission::PAYROLL_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'employee_id'   => ['required', 'integer', 'exists:employees,id'],
            'period_start'  => ['required', 'date'],
            'period_end'    => ['required', 'date', 'after_or_equal:period_start'],
            'gross_salary'  => ['required', 'numeric', 'min:1', 'max:9999999'],
            'currency_code' => ['required', 'in:TRY,USD,EUR'],
        ]);

        $gross = (float) $validated['gross_salary'];
        $sgk   = round($gross * 0.14, 2);
        $tax   = round(($gross - $sgk) * 0.15, 2);
        $net   = round($gross - $sgk - $tax, 2);

        Gate::authorize('create', Payroll::class);
        Payroll::query()->create([
            'employee_id'   => (int) $validated['employee_id'],
            'period_start'  => $validated['period_start'],
            'period_end'    => $validated['period_end'],
            'gross_salary'  => $gross,
            'deductions'    => [
                'sgk_employee' => $sgk,
                'income_tax'   => $tax,
            ],
            'net_salary'    => $net,
            'currency_code' => $validated['currency_code'],
            'status'        => PayrollStatus::Draft->value,
        ]);

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    /** Maker-Checker: admin approves draft payroll */
    public function approve(int $id): void
    {
        $payroll = Payroll::query()->findOrFail($id);
        Gate::authorize('approve', $payroll);

        $user = auth()->user();
        if (! ($user instanceof \App\Models\User)) { abort(403); }

        $payroll->update([
            'status'      => PayrollStatus::Approved->value,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        // Email notification to employee if they have a user account with email
        $payroll->load('employee.user');
        $employeeEmail = $payroll->employee?->user?->email ?? $payroll->employee?->email;
        if ($employeeEmail) {
            Mail::to($employeeEmail)->queue(new PayrollApprovedMail($payroll));
        }

        session()->flash('success', __('Payroll #:id approved.', ['id' => $id]));
    }

    /** Mark as paid */
    public function markPaid(int $id): void
    {
        $payroll = Payroll::query()->findOrFail($id);
        Gate::authorize('approve', $payroll);

        $payroll->update([
            'status'  => PayrollStatus::Paid->value,
            'paid_at' => now(),
        ]);

        session()->flash('success', __('Payroll #:id marked as paid.', ['id' => $id]));
    }

    public function delete(int $id): void
    {
        $payroll = Payroll::query()->findOrFail($id);
        Gate::authorize('delete', $payroll);
        $payroll->delete();
        $this->resetPage();
    }

    /** Toplu bordro: seçili ay + ortak brüt ücret ile tüm çalışanlara draft bordro oluşturur (zaten varsa atlar). */
    public function bulkGenerate(): void
    {
        $user = auth()->user();
        if (! ($user instanceof \App\Models\User) || ! LogisticsPermission::canWrite($user, LogisticsPermission::PAYROLL_WRITE)) {
            abort(403);
        }

        $this->validate([
            'bulkPeriodMonth'  => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'bulkGrossSalary'  => ['required', 'numeric', 'min:1', 'max:9999999'],
            'bulkCurrencyCode' => ['required', 'in:TRY,USD,EUR'],
        ]);

        [$year, $month] = explode('-', $this->bulkPeriodMonth);
        $periodStart = \Carbon\Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
        $periodEnd   = $periodStart->copy()->endOfMonth();

        $gross = (float) $this->bulkGrossSalary;
        $sgk   = round($gross * 0.14, 2);
        $tax   = round(($gross - $sgk) * 0.15, 2);
        $net   = round($gross - $sgk - $tax, 2);

        $employees = Employee::query()->get();
        $created   = 0;
        $skipped   = 0;

        foreach ($employees as $emp) {
            $exists = Payroll::query()
                ->where('employee_id', $emp->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Payroll::query()->create([
                'employee_id'   => $emp->id,
                'period_start'  => $periodStart->toDateString(),
                'period_end'    => $periodEnd->toDateString(),
                'gross_salary'  => $gross,
                'deductions'    => ['sgk_employee' => $sgk, 'income_tax' => $tax],
                'net_salary'    => $net,
                'currency_code' => $this->bulkCurrencyCode,
                'status'        => PayrollStatus::Draft->value,
            ]);
            $created++;
        }

        $this->showBulkGenerate = false;
        $this->bulkGrossSalary  = '';
        $this->resetPage();
        session()->flash('success', __('Bulk generate complete: :created created, :skipped skipped.', [
            'created' => $created,
            'skipped' => $skipped,
        ]));
    }

    private function resetForm(): void
    {
        $this->employee_id   = '';
        $this->period_start  = now()->startOfMonth()->format('Y-m-d');
        $this->period_end    = now()->endOfMonth()->format('Y-m-d');
        $this->gross_salary  = '';
        $this->currency_code = 'TRY';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser   = auth()->user();
        $canWrite   = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::PAYROLL_WRITE);
        $canApprove = $authUser instanceof \App\Models\User
            && $authUser->can(\App\Authorization\LogisticsPermission::ADMIN);
    @endphp

    @if (session()->has('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success">{{ session('bulk_deleted') }}</flux:callout>
    @endif

    <x-admin.page-header
        :heading="__('Payroll')"
        :description="__('Manage employee payroll with draft → approved → paid lifecycle.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <x-admin.index-actions>
                    <x-slot name="extra">
                        <flux:button type="button" variant="outline" wire:click="$set('showBulkGenerate', true)" icon="squares-plus">
                            {{ __('Bulk generate') }}
                        </flux:button>
                    </x-slot>
                    <x-slot name="primary">
                        <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                            {{ __('New payroll entry') }}
                        </flux:button>
                    </x-slot>
                </x-admin.index-actions>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- Bulk Generate Panel --}}
    @if ($showBulkGenerate)
        <flux:card class="p-4 border-2 border-blue-200 dark:border-blue-800">
            <flux:heading size="sm" class="mb-3">{{ __('Bulk generate payrolls') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-500">
                {{ __('Creates draft payrolls for all employees with a base salary for the selected month. Existing records are skipped.') }}
            </flux:text>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <flux:label>{{ __('Period (YYYY-MM)') }}</flux:label>
                    <flux:input type="month" wire:model="bulkPeriodMonth" />
                </div>
                <div>
                    <flux:label>{{ __('Gross salary') }}</flux:label>
                    <flux:input type="number" wire:model="bulkGrossSalary" min="1" step="0.01" placeholder="0.00" class="w-40" />
                </div>
                <div>
                    <flux:label>{{ __('Currency') }}</flux:label>
                    <flux:select wire:model="bulkCurrencyCode" class="w-28">
                        <option value="TRY">TRY</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </flux:select>
                </div>
                <flux:button type="button" variant="primary" wire:click="bulkGenerate" wire:loading.attr="disabled">
                    {{ __('Generate') }}
                </flux:button>
                <flux:button type="button" variant="ghost" wire:click="$set('showBulkGenerate', false)">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </flux:card>
    @endif

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Draft') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['draft'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['draft'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Approved') }}</flux:text>
            <flux:heading size="lg" class="text-blue-500">{{ $this->kpiStats['approved'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Gross this month') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['gross_this_month'], 2) }} ₺</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Net paid this month') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ number_format($this->kpiStats['paid_this_month'], 2) }} ₺</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <x-admin.filter-bar :label="__('Advanced filters')">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <flux:select wire:model.live="filterEmployee" :label="__('Employee')" class="max-w-[220px]">
                <option value="">{{ __('All employees') }}</option>
                @foreach ($this->employees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Enums\PayrollStatus::cases() as $ps)
                    <option value="{{ $ps->value }}">{{ $ps->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="filterPeriod" type="month" :label="__('Period')" class="max-w-[180px]" />
        @endif
    </x-admin.filter-bar>

    {{-- Create Form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('New payroll entry') }}</flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="employee_id" :label="__('Employee')" required>
                    <option value="">{{ __('Select employee...') }}</option>
                    @foreach ($this->employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="period_start" type="date" :label="__('Period start')" required />
                <flux:input wire:model="period_end" type="date" :label="__('Period end')" required />
                <flux:input wire:model="gross_salary" type="text" :label="__('Gross salary')" required placeholder="0.00" />
                <flux:select wire:model="currency_code" :label="__('Currency')">
                    <option value="TRY">TRY — Türk Lirası</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </flux:select>

                {{-- Auto-calculated preview --}}
                @if (filled($gross_salary) && is_numeric($gross_salary))
                    @php
                        $g = (float) $gross_salary;
                        $sgk = round($g * 0.14, 2);
                        $tax = round(($g - $sgk) * 0.15, 2);
                        $net = round($g - $sgk - $tax, 2);
                    @endphp
                    <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800 lg:col-span-3">
                        <p class="font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('Auto-calculated deductions') }}</p>
                        <div class="grid grid-cols-3 gap-2 text-zinc-600 dark:text-zinc-400">
                            <span>SGK (14%): <strong>{{ number_format($sgk, 2) }}</strong></span>
                            <span>{{ __('Income Tax') }} (15%): <strong>{{ number_format($tax, 2) }}</strong></span>
                            <span class="text-green-600">{{ __('Net') }}: <strong>{{ number_format($net, 2) }}</strong></span>
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save as draft') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Bulk delete bar --}}
    @if (count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button type="button" variant="danger" wire:click="bulkDeleteSelected" wire:confirm="{{ __('Delete selected records?') }}">{{ __('Delete selected') }}</flux:button>
        </div>
    @endif

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">
                            <input type="checkbox"
                                wire:click="toggleSelectPage"
                                @checked($this->isPageFullySelected())
                            >
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Employee') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('period_start')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Period') }}@if ($sortColumn === 'period_start') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium text-end">
                            <button wire:click="sortBy('gross_salary')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Gross') }}@if ($sortColumn === 'gross_salary') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Deductions') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">
                            <button wire:click="sortBy('net_salary')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Net') }}@if ($sortColumn === 'net_salary') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('status')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Status') }}@if ($sortColumn === 'status') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Approved by') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedPayrolls as $payroll)
                        <tr>
                            <td class="py-2 pe-3">
                                <input type="checkbox" wire:model.live="selectedIds" value="{{ $payroll->id }}">
                            </td>
                            <td class="py-2 pe-3 font-medium">{{ $payroll->employee?->fullName() }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs text-zinc-500">
                                {{ $payroll->period_start->format('d M Y') }} – {{ $payroll->period_end->format('d M') }}
                            </td>
                            <td class="py-2 pe-3 text-end font-mono">{{ number_format((float)$payroll->gross_salary, 2) }}</td>
                            <td class="py-2 pe-3 text-end font-mono text-red-400">
                                {{ number_format($payroll->totalDeductions(), 2) }}
                            </td>
                            <td class="py-2 pe-3 text-end font-mono font-semibold text-green-600">
                                {{ number_format((float)$payroll->net_salary, 2) }}
                                <span class="text-xs font-normal text-zinc-400">{{ $payroll->currency_code }}</span>
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $payroll->status->color() }}" size="sm">{{ $payroll->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 text-xs text-zinc-500">
                                {{ $payroll->approvedBy?->name ?? '—' }}
                                @if ($payroll->approved_at)
                                    <br><span class="text-zinc-400">{{ $payroll->approved_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-end">
                                @if ($canApprove && $payroll->status->isDraft())
                                    <flux:button size="sm" variant="primary"
                                        wire:click="confirmAction({{ $payroll->id }}, 'approve')"
                                    >{{ __('Approve') }}</flux:button>
                                @endif
                                @if ($canApprove && $payroll->status->isApproved())
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="confirmAction({{ $payroll->id }}, 'paid')"
                                    >{{ __('Mark paid') }}</flux:button>
                                @endif
                                @if (! $payroll->status->isDraft())
                                    <a href="{{ route('admin.hr.payroll.print', $payroll) }}" target="_blank"
                                        class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                        🖨️ {{ __('Print') }}
                                    </a>
                                @endif
                                @if ($canWrite && $payroll->status->isDraft())
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="confirmAction({{ $payroll->id }}, 'delete')"
                                    >{{ __('Delete') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-zinc-500">
                                {{ __('No payroll entries yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedPayrolls->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-action" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @if ($confirmingAction === 'approve') {{ __('Approve this payroll entry?') }}
                    @elseif ($confirmingAction === 'paid') {{ __('Mark as paid?') }}
                    @else {{ __('Delete this payroll entry?') }}
                    @endif
                </flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    :variant="$confirmingAction === 'delete' ? 'danger' : 'primary'"
                    wire:click="executeAction"
                >
                    @if ($confirmingAction === 'approve') {{ __('Approve') }}
                    @elseif ($confirmingAction === 'paid') {{ __('Mark paid') }}
                    @else {{ __('Delete') }}
                    @endif
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
