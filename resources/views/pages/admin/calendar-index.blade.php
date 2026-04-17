<?php

use App\Models\Leave;
use App\Models\MaintenanceSchedule;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Calendar')] class extends Component
{
    public int $year;
    public int $month;

    public bool $showMaintenance = true;
    public bool $showLeaves      = true;
    public bool $showPayments    = true;
    public bool $showOrders      = true;

    public function mount(): void
    {
        Gate::authorize('viewAny', \App\Models\MaintenanceSchedule::class);
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function prevMonth(): void
    {
        $dt = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year  = $dt->year;
        $this->month = $dt->month;
    }

    public function nextMonth(): void
    {
        $dt = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year  = $dt->year;
        $this->month = $dt->month;
    }

    public function goToday(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    /**
     * Returns events grouped by date string (Y-m-d).
     *
     * @return array<string, array<int, array{type:string, color:string, label:string, href:string|null}>>
     */
    #[Computed]
    public function eventsByDate(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $events = [];

        if ($this->showMaintenance) {
            MaintenanceSchedule::query()
                ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
                ->get(['id', 'title', 'scheduled_date', 'status'])
                ->each(function ($m) use (&$events): void {
                    $key = Carbon::parse($m->scheduled_date)->format('Y-m-d');
                    $events[$key][] = [
                        'type'  => 'maintenance',
                        'color' => 'blue',
                        'label' => $m->title,
                        'href'  => route('admin.maintenance.index'),
                    ];
                });
        }

        if ($this->showLeaves) {
            Leave::query()
                ->where('start_date', '<=', $end->toDateString())
                ->where('end_date', '>=', $start->toDateString())
                ->whereIn('status', ['pending', 'approved'])
                ->with('employee:id,name')
                ->get(['id', 'employee_id', 'start_date', 'end_date', 'status'])
                ->each(function ($l) use ($start, $end, &$events): void {
                    $leaveStart = Carbon::parse($l->start_date)->max($start);
                    $leaveEnd   = Carbon::parse($l->end_date)->min($end);
                    $current    = $leaveStart->copy();
                    while ($current->lte($leaveEnd)) {
                        $key = $current->format('Y-m-d');
                        $events[$key][] = [
                            'type'  => 'leave',
                            'color' => 'yellow',
                            'label' => $l->employee?->name ?? __('Leave'),
                            'href'  => route('admin.leaves.index'),
                        ];
                        $current->addDay();
                    }
                });
        }

        if ($this->showPayments) {
            Payment::query()
                ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('status', ['pending', 'overdue'])
                ->get(['id', 'due_date', 'amount', 'currency_code'])
                ->each(function ($p) use (&$events): void {
                    $key = Carbon::parse($p->due_date)->format('Y-m-d');
                    $events[$key][] = [
                        'type'  => 'payment',
                        'color' => 'red',
                        'label' => number_format((float) $p->amount, 0) . ' ' . $p->currency_code,
                        'href'  => route('admin.payments.index'),
                    ];
                });
        }

        if ($this->showOrders) {
            Order::query()
                ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                ->whereNotNull('due_date')
                ->get(['id', 'order_number', 'due_date', 'status'])
                ->each(function ($o) use (&$events): void {
                    $key = Carbon::parse($o->due_date)->format('Y-m-d');
                    $events[$key][] = [
                        'type'  => 'order',
                        'color' => 'green',
                        'label' => $o->order_number ?? __('Order'),
                        'href'  => route('admin.orders.index'),
                    ];
                });
        }

        return $events;
    }

    /**
     * @return array<int, array{date: Carbon, isCurrentMonth: bool, isToday: bool}>
     */
    #[Computed]
    public function calendarDays(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // Pad to Monday start
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd   = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $days   = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $days[] = [
                'date'           => $cursor->copy(),
                'isCurrentMonth' => $cursor->month === $this->month,
                'isToday'        => $cursor->isToday(),
            ];
            $cursor->addDay();
        }

        return $days;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Calendar')"
        :description="__('Operational overview: maintenance, leaves, payments and order deadlines.')"
    />

    {{-- Month Nav + Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">

            {{-- Navigation --}}
            <div class="flex items-center gap-2">
                <flux:button type="button" variant="ghost" size="sm" icon="chevron-left" wire:click="prevMonth" />
                <span class="min-w-[160px] text-center text-base font-semibold">
                    {{ \Carbon\Carbon::create($this->year, $this->month, 1)->isoFormat('MMMM YYYY') }}
                </span>
                <flux:button type="button" variant="ghost" size="sm" icon="chevron-right" wire:click="nextMonth" />
                <flux:button type="button" variant="ghost" size="sm" wire:click="goToday">{{ __('Today') }}</flux:button>
            </div>

            {{-- Event type toggles --}}
            <div class="flex flex-wrap gap-3 text-sm">
                <label class="flex cursor-pointer items-center gap-1.5">
                    <input type="checkbox" wire:model.live="showMaintenance" class="rounded border-zinc-300" />
                    <span class="inline-block size-2.5 rounded-full bg-blue-500"></span>
                    {{ __('Maintenance') }}
                </label>
                <label class="flex cursor-pointer items-center gap-1.5">
                    <input type="checkbox" wire:model.live="showLeaves" class="rounded border-zinc-300" />
                    <span class="inline-block size-2.5 rounded-full bg-yellow-400"></span>
                    {{ __('Leaves') }}
                </label>
                <label class="flex cursor-pointer items-center gap-1.5">
                    <input type="checkbox" wire:model.live="showPayments" class="rounded border-zinc-300" />
                    <span class="inline-block size-2.5 rounded-full bg-red-500"></span>
                    {{ __('Payments') }}
                </label>
                <label class="flex cursor-pointer items-center gap-1.5">
                    <input type="checkbox" wire:model.live="showOrders" class="rounded border-zinc-300" />
                    <span class="inline-block size-2.5 rounded-full bg-green-500"></span>
                    {{ __('Orders') }}
                </label>
            </div>
        </div>
    </flux:card>

    {{-- Calendar Grid --}}
    <flux:card class="overflow-hidden p-0">
        {{-- Day headers --}}
        <div class="grid grid-cols-7 divide-x divide-zinc-200 border-b border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                <div class="bg-zinc-50 py-2 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    {{ __($dayName) }}
                </div>
            @endforeach
        </div>

        {{-- Day cells --}}
        <div class="grid grid-cols-7 divide-x divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->calendarDays as $day)
                @php
                    $dateKey = $day['date']->format('Y-m-d');
                    $dayEvents = $this->eventsByDate[$dateKey] ?? [];
                @endphp
                <div @class([
                    'min-h-[96px] border-b border-zinc-200 dark:border-zinc-700 p-1.5',
                    'bg-white dark:bg-zinc-900' => $day['isCurrentMonth'] && !$day['isToday'],
                    'bg-zinc-50 dark:bg-zinc-800/50' => !$day['isCurrentMonth'],
                    'bg-blue-50 dark:bg-blue-950/30' => $day['isToday'],
                ])>
                    <div @class([
                        'mb-1 flex size-6 items-center justify-center rounded-full text-xs font-medium',
                        'text-zinc-400 dark:text-zinc-600' => !$day['isCurrentMonth'] && !$day['isToday'],
                        'text-zinc-700 dark:text-zinc-300' => $day['isCurrentMonth'] && !$day['isToday'],
                        'bg-blue-600 text-white' => $day['isToday'],
                    ])>
                        {{ $day['date']->day }}
                    </div>

                    <div class="flex flex-col gap-0.5">
                        @foreach (array_slice($dayEvents, 0, 3) as $event)
                            @php
                                $bgClasses = match($event['color']) {
                                    'blue'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                    'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    'red'    => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    'green'  => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    default  => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300',
                                };
                            @endphp
                            @if ($event['href'])
                                <a href="{{ $event['href'] }}" wire:navigate
                                   class="block truncate rounded px-1 py-0.5 text-[11px] leading-tight font-medium {{ $bgClasses }} hover:opacity-80">
                                    {{ $event['label'] }}
                                </a>
                            @else
                                <span class="block truncate rounded px-1 py-0.5 text-[11px] leading-tight font-medium {{ $bgClasses }}">
                                    {{ $event['label'] }}
                                </span>
                            @endif
                        @endforeach
                        @if (count($dayEvents) > 3)
                            <span class="px-1 text-[10px] text-zinc-400">
                                +{{ count($dayEvents) - 3 }} {{ __('more') }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-4 text-xs text-zinc-500">
        <span class="flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-blue-500"></span> {{ __('Maintenance scheduled') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-yellow-400"></span> {{ __('Employee leave') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-red-500"></span> {{ __('Payment due') }}</span>
        <span class="flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-green-500"></span> {{ __('Order deadline') }}</span>
    </div>

</div>
