<?php

use App\Enums\AdvanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\PayrollStatus;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payroll;
use App\Services\HR\DriverPerformanceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('My Dashboard')] class extends Component
{
    public function mount(): void
    {
        if (! auth()->user()->employee_id) {
            abort(403);
        }
    }

    private function employeeId(): int
    {
        return (int) auth()->user()->employee_id;
    }

    /**
     * @return array{pending_leaves:int, approved_leaves_this_year:int, pending_advances:int, last_net_salary:float|null}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $eid = $this->employeeId();

        $lastPayroll = Payroll::query()
            ->where('employee_id', $eid)
            ->whereIn('status', [PayrollStatus::Approved->value, PayrollStatus::Paid->value])
            ->orderByDesc('period_start')
            ->first();

        return [
            'pending_leaves'            => Leave::query()
                ->where('employee_id', $eid)
                ->where('status', LeaveStatus::Pending->value)
                ->count(),
            'approved_leaves_this_year' => Leave::query()
                ->where('employee_id', $eid)
                ->where('status', LeaveStatus::Approved->value)
                ->whereYear('start_date', now()->year)
                ->sum('days_count'),
            'pending_advances'          => Advance::query()
                ->where('employee_id', $eid)
                ->where('status', AdvanceStatus::Pending->value)
                ->count(),
            'last_net_salary'           => $lastPayroll ? (float) $lastPayroll->net_salary : null,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Leave>
     */
    #[Computed]
    public function recentLeaves(): \Illuminate\Database\Eloquent\Collection
    {
        return Leave::query()
            ->where('employee_id', $this->employeeId())
            ->orderByDesc('start_date')
            ->limit(5)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Payroll>
     */
    #[Computed]
    public function recentPayrolls(): \Illuminate\Database\Eloquent\Collection
    {
        return Payroll::query()
            ->where('employee_id', $this->employeeId())
            ->whereIn('status', [PayrollStatus::Approved->value, PayrollStatus::Paid->value])
            ->orderByDesc('period_start')
            ->limit(3)
            ->get();
    }

    /**
     * Giriş yapan kullanıcının şoför performans skoru (is_driver = true ise).
     *
     * @return array{score: int, deliveries_90d: int, expired_docs: int, grade: string}|null
     */
    #[Computed]
    public function myDriverPerformance(): ?array
    {
        $employee = Employee::query()->find($this->employeeId());

        if (! $employee || ! $employee->is_driver) {
            return null;
        }

        return app(DriverPerformanceService::class)->scoreForEmployee($employee);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Welcome back').', '.auth()->user()->name"
        :description="__('Your self-service HR portal')"
    />

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending leave requests') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pending_leaves'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pending_leaves'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Leave days used (this year)') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['approved_leaves_this_year'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending advance requests') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pending_advances'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pending_advances'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Last net salary') }}</flux:text>
            <flux:heading size="lg">
                {{ $this->kpiStats['last_net_salary'] !== null
                    ? number_format($this->kpiStats['last_net_salary'], 2).' ₺'
                    : '—' }}
            </flux:heading>
        </flux:card>
        @if ($this->myDriverPerformance !== null)
            @php
                $perf = $this->myDriverPerformance;
                $gradeColor = match ($perf['grade']) { 'A' => 'text-green-600', 'B' => 'text-blue-600', 'C' => 'text-yellow-500', default => 'text-red-500' };
            @endphp
            <flux:card class="p-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Driver score') }}</flux:text>
                <div class="flex items-baseline gap-2">
                    <flux:heading size="lg" class="{{ $gradeColor }}">{{ $perf['score'] }}</flux:heading>
                    <span class="text-sm font-bold {{ $gradeColor }}">{{ $perf['grade'] }}</span>
                </div>
                <flux:text class="mt-1 text-xs text-zinc-500">
                    {{ $perf['deliveries_90d'] }} {{ __('deliveries (90d)') }}
                    @if ($perf['expired_docs'] > 0)
                        · <span class="text-red-500">{{ $perf['expired_docs'] }} {{ __('expired docs') }}</span>
                    @endif
                </flux:text>
            </flux:card>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Recent Leave Requests --}}
        <flux:card class="p-4">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="sm">{{ __('Recent leave requests') }}</flux:heading>
                <flux:link :href="route('personnel.leaves.index')" wire:navigate class="text-sm">
                    {{ __('View all') }} →
                </flux:link>
            </div>
            @forelse ($this->recentLeaves as $leave)
                <div class="flex items-center justify-between border-b border-zinc-100 py-2 last:border-0 dark:border-zinc-800">
                    <div>
                        <div class="text-sm font-medium">{{ $leave->type->label() }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}
                            ({{ $leave->days_count }} {{ __('days') }})
                        </div>
                    </div>
                    <flux:badge color="{{ $leave->status->color() }}" size="sm">{{ $leave->status->label() }}</flux:badge>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500">{{ __('No leave requests yet.') }}</flux:text>
            @endforelse
        </flux:card>

        {{-- Recent Payrolls --}}
        <flux:card class="p-4">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="sm">{{ __('Recent payslips') }}</flux:heading>
                <flux:link :href="route('personnel.payrolls.index')" wire:navigate class="text-sm">
                    {{ __('View all') }} →
                </flux:link>
            </div>
            @forelse ($this->recentPayrolls as $payroll)
                <div class="flex items-center justify-between border-b border-zinc-100 py-2 last:border-0 dark:border-zinc-800">
                    <div>
                        <div class="text-sm font-medium">
                            {{ $payroll->period_start?->format('M Y') }}
                            @if ($payroll->period_start && $payroll->period_end && $payroll->period_start->format('Y-m') !== $payroll->period_end->format('Y-m'))
                                – {{ $payroll->period_end->format('M Y') }}
                            @endif
                        </div>
                        <div class="text-xs text-zinc-500">{{ __('Net') }}: {{ number_format((float) $payroll->net_salary, 2) }} {{ $payroll->currency_code }}</div>
                    </div>
                    <flux:badge color="{{ $payroll->status->color() }}" size="sm">{{ $payroll->status->label() }}</flux:badge>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500">{{ __('No payslips yet.') }}</flux:text>
            @endforelse
        </flux:card>
    </div>

    {{-- Quick links --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800">
            <flux:link :href="route('personnel.leaves.index')" wire:navigate class="flex flex-col gap-1 no-underline">
                <flux:icon.calendar class="size-6 text-blue-500" />
                <flux:heading size="sm">{{ __('Request leave') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Submit a new leave request') }}</flux:text>
            </flux:link>
        </flux:card>
        <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800">
            <flux:link :href="route('personnel.advances.index')" wire:navigate class="flex flex-col gap-1 no-underline">
                <flux:icon.banknotes class="size-6 text-green-500" />
                <flux:heading size="sm">{{ __('Request advance') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Submit a new salary advance request') }}</flux:text>
            </flux:link>
        </flux:card>
        <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800">
            <flux:link :href="route('personnel.payrolls.index')" wire:navigate class="flex flex-col gap-1 no-underline">
                <flux:icon.document-text class="size-6 text-purple-500" />
                <flux:heading size="sm">{{ __('My payslips') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('View and download your payslips') }}</flux:text>
            </flux:link>
        </flux:card>
        <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800">
            <flux:link :href="route('personnel.shifts.index')" wire:navigate class="flex flex-col gap-1 no-underline">
                <flux:icon.clock class="size-6 text-orange-500" />
                <flux:heading size="sm">{{ __('My shifts') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('View your scheduled shifts') }}</flux:text>
            </flux:link>
        </flux:card>
        <flux:card class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800">
            <flux:link :href="route('personnel.profile')" wire:navigate class="flex flex-col gap-1 no-underline">
                <flux:icon.user-circle class="size-6 text-zinc-400" />
                <flux:heading size="sm">{{ __('My Profile') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('View your employee record') }}</flux:text>
            </flux:link>
        </flux:card>
    </div>
</div>
