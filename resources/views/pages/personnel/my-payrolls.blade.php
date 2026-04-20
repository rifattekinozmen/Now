<?php

use App\Enums\PayrollStatus;
use App\Models\Payroll;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Payslips')] class extends Component
{
    use WithPagination;

    public string $filterYear = '';

    public function mount(): void
    {
        if (! auth()->user()->employee_id) {
            abort(403);
        }
        $this->filterYear = (string) now()->year;
    }

    public function updatedFilterYear(): void
    {
        $this->resetPage();
    }

    private function employeeId(): int
    {
        return (int) auth()->user()->employee_id;
    }

    /**
     * @return array{total:int, paid:int, total_net:float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $eid = $this->employeeId();

        return [
            'total'     => Payroll::query()->where('employee_id', $eid)->count(),
            'paid'      => Payroll::query()->where('employee_id', $eid)->where('status', PayrollStatus::Paid->value)->count(),
            'total_net' => (float) Payroll::query()
                ->where('employee_id', $eid)
                ->where('status', PayrollStatus::Paid->value)
                ->whereYear('period_start', now()->year)
                ->sum('net_salary'),
        ];
    }

    #[Computed]
    public function paginatedPayrolls(): LengthAwarePaginator
    {
        $q = Payroll::query()
            ->where('employee_id', $this->employeeId())
            ->whereNot('status', PayrollStatus::Draft->value);

        if ($this->filterYear !== '') {
            $q->whereYear('period_start', (int) $this->filterYear);
        }

        return $q->orderByDesc('period_start')->paginate(20);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My Payslips') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('View your payroll history and download payslips.') }}</flux:text>
        </div>
        <flux:link :href="route('personnel.dashboard')" wire:navigate variant="ghost">
            ← {{ __('Dashboard') }}
        </flux:link>
    </div>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total payslips') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Paid') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['paid'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Net paid this year') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['total_net'], 2) }} ₺</flux:heading>
        </flux:card>
    </div>

    {{-- Filter --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="filterYear" :label="__('Year')" class="max-w-[140px]">
            <option value="">{{ __('All years') }}</option>
            @foreach (range(now()->year, now()->year - 4) as $year)
                <option value="{{ $year }}">{{ $year }}</option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">{{ __('Period') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Gross') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Deductions') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Net') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Paid at') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Payslip') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedPayrolls as $payroll)
                        <tr>
                            <td class="py-2 pe-3 font-medium">
                                {{ $payroll->period_start?->format('M Y') }}
                                @if ($payroll->period_start && $payroll->period_end &&
                                     $payroll->period_start->format('Y-m') !== $payroll->period_end->format('Y-m'))
                                    – {{ $payroll->period_end->format('M Y') }}
                                @endif
                            </td>
                            <td class="py-2 pe-3 text-end font-mono text-xs">
                                {{ number_format((float) $payroll->gross_salary, 2) }} {{ $payroll->currency_code }}
                            </td>
                            <td class="py-2 pe-3 text-end font-mono text-xs text-red-600">
                                {{ number_format($payroll->totalDeductions(), 2) }} {{ $payroll->currency_code }}
                            </td>
                            <td class="py-2 pe-3 text-end font-mono text-xs font-semibold">
                                {{ number_format((float) $payroll->net_salary, 2) }} {{ $payroll->currency_code }}
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $payroll->status->color() }}" size="sm">{{ $payroll->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs text-zinc-500">
                                {{ $payroll->paid_at?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="py-2 text-end">
                                @if ($payroll->status !== \App\Enums\PayrollStatus::Draft)
                                    <flux:link
                                        :href="route('personnel.payrolls.print', $payroll)"
                                        target="_blank"
                                        class="text-xs"
                                    >
                                        {{ __('Print') }} ↗
                                    </flux:link>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                {{ __('No payslips found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedPayrolls->links() }}</div>
    </flux:card>
</div>
