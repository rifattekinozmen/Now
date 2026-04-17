<?php

use App\Models\Employee;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('My Profile')] class extends Component
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

    #[Computed]
    public function employee(): Employee
    {
        return Employee::query()->findOrFail($this->employeeId());
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My Profile') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Your employee record on file.') }}</flux:text>
        </div>
        <flux:link :href="route('personnel.dashboard')" wire:navigate variant="ghost">
            ← {{ __('Dashboard') }}
        </flux:link>
    </div>

    {{-- Identity card --}}
    <flux:card class="p-6">
        <div class="flex items-center gap-4 mb-6">
            <x-avatar :name="$this->employee->fullName()" class="size-14 text-xl" />
            <div>
                <flux:heading size="lg">{{ $this->employee->fullName() }}</flux:heading>
                @if ($this->employee->is_driver)
                    <flux:badge color="blue" size="sm" class="mt-1">{{ __('Driver') }}</flux:badge>
                @endif
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Contact --}}
            <div>
                <flux:text class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">
                    {{ __('Contact') }}
                </flux:text>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-zinc-500">{{ __('Email') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-zinc-200 break-all">{{ $this->employee->email ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-zinc-500">{{ __('Phone') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ $this->employee->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-32 shrink-0 text-zinc-500">{{ __('Blood group') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ $this->employee->blood_group ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Driver credentials --}}
            @if ($this->employee->is_driver)
                <div>
                    <flux:text class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">
                        {{ __('Driver credentials') }}
                    </flux:text>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-zinc-500">{{ __('License class') }}</dt>
                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">{{ $this->employee->license_class ?? '—' }}</dd>
                        </div>

                        <div class="flex items-center gap-2">
                            <dt class="w-32 shrink-0 text-zinc-500">{{ __('License valid') }}</dt>
                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $this->employee->license_valid_until?->format('d M Y') ?? '—' }}
                                @if ($this->employee->license_valid_until?->isPast())
                                    <flux:badge color="red" size="sm" class="ms-1">{{ __('Expired') }}</flux:badge>
                                @elseif ($this->employee->license_valid_until?->diffInDays() <= 30)
                                    <flux:badge color="yellow" size="sm" class="ms-1">{{ __('Expiring soon') }}</flux:badge>
                                @endif
                            </dd>
                        </div>

                        <div class="flex items-center gap-2">
                            <dt class="w-32 shrink-0 text-zinc-500">{{ __('SRC valid') }}</dt>
                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $this->employee->src_valid_until?->format('d M Y') ?? '—' }}
                                @if ($this->employee->src_valid_until?->isPast())
                                    <flux:badge color="red" size="sm" class="ms-1">{{ __('Expired') }}</flux:badge>
                                @elseif ($this->employee->src_valid_until?->diffInDays() <= 30)
                                    <flux:badge color="yellow" size="sm" class="ms-1">{{ __('Expiring soon') }}</flux:badge>
                                @endif
                            </dd>
                        </div>

                        <div class="flex items-center gap-2">
                            <dt class="w-32 shrink-0 text-zinc-500">{{ __('Psychotechnical') }}</dt>
                            <dd class="font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $this->employee->psychotechnical_valid_until?->format('d M Y') ?? '—' }}
                                @if ($this->employee->psychotechnical_valid_until?->isPast())
                                    <flux:badge color="red" size="sm" class="ms-1">{{ __('Expired') }}</flux:badge>
                                @elseif ($this->employee->psychotechnical_valid_until?->diffInDays() <= 30)
                                    <flux:badge color="yellow" size="sm" class="ms-1">{{ __('Expiring soon') }}</flux:badge>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    </flux:card>

    {{-- Quick links --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <flux:link :href="route('personnel.leaves.index')" wire:navigate class="flex flex-col gap-1 no-underline">
            <flux:card class="flex items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <flux:icon name="calendar-days" class="size-5 text-zinc-400" />
                <span class="text-sm font-medium">{{ __('My Leaves') }}</span>
            </flux:card>
        </flux:link>
        <flux:link :href="route('personnel.payrolls.index')" wire:navigate class="flex flex-col gap-1 no-underline">
            <flux:card class="flex items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <flux:icon name="document-text" class="size-5 text-zinc-400" />
                <span class="text-sm font-medium">{{ __('My Payslips') }}</span>
            </flux:card>
        </flux:link>
        <flux:link :href="route('personnel.advances.index')" wire:navigate class="flex flex-col gap-1 no-underline">
            <flux:card class="flex items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <flux:icon name="banknotes" class="size-5 text-zinc-400" />
                <span class="text-sm font-medium">{{ __('My Advances') }}</span>
            </flux:card>
        </flux:link>
        <flux:link :href="route('personnel.shifts.index')" wire:navigate class="flex flex-col gap-1 no-underline">
            <flux:card class="flex items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <flux:icon name="clock" class="size-5 text-zinc-400" />
                <span class="text-sm font-medium">{{ __('My Shifts') }}</span>
            </flux:card>
        </flux:link>
    </div>
</div>
