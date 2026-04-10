<?php

use App\Models\Order;
use App\Services\Finance\CashFlowProjectionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Payment due calendar')] class extends Component
{
    /** @var string `Y-m` */
    public string $month = '';

    public ?string $selectedDate = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
        if ($this->month === '') {
            $this->month = now()->format('Y-m');
        }
    }

    public function previousMonth(): void
    {
        $this->selectedDate = null;
        $this->month = Carbon::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->selectedDate = null;
        $this->month = Carbon::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    public function selectDate(?string $date): void
    {
        $this->selectedDate = $date;
    }

    /**
     * @return list<array{order_id: int, order_number: string, due_date: string, amount: string|null, currency_code: string|null, customer_name: string|null}>
     */
    #[Computed]
    public function projectionsForMonth(): array
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            return [];
        }

        $first = Carbon::parse($this->month.'-01')->startOfDay();
        $last = $first->copy()->endOfMonth();

        $svc = app(CashFlowProjectionService::class);

        return $svc->projectForTenant(
            (int) $user->tenant_id,
            $first,
            $last
        );
    }

    /**
     * @return array<string, list<array{order_id: int, order_number: string, due_date: string, amount: string|null, currency_code: string|null, customer_name: string|null}>>
     */
    #[Computed]
    public function projectionsByDueDate(): array
    {
        $by = [];
        foreach ($this->projectionsForMonth as $row) {
            $d = $row['due_date'];
            if (! isset($by[$d])) {
                $by[$d] = [];
            }
            $by[$d][] = $row;
        }

        return $by;
    }

    /**
     * @return list<list<array{type: string, date?: string, day?: int, count?: int}|null>>
     */
    #[Computed]
    public function calendarWeeks(): array
    {
        $first = Carbon::parse($this->month.'-01')->startOfDay();
        $last = $first->copy()->endOfMonth();
        $byDate = $this->projectionsByDueDate;

        $weeks = [];
        $current = [];
        $pad = $first->dayOfWeekIso - 1;
        for ($i = 0; $i < $pad; $i++) {
            $current[] = null;
        }

        $cursor = $first->copy();
        while ($cursor->lte($last)) {
            $dateStr = $cursor->toDateString();
            $count = count($byDate[$dateStr] ?? []);
            $current[] = [
                'type' => 'day',
                'date' => $dateStr,
                'day' => (int) $cursor->day,
                'count' => $count,
            ];
            if (count($current) === 7) {
                $weeks[] = $current;
                $current = [];
            }
            $cursor->addDay();
        }

        if (count($current) > 0) {
            while (count($current) < 7) {
                $current[] = null;
            }
            $weeks[] = $current;
        }

        return $weeks;
    }

    #[Computed]
    public function calendarHeading(): string
    {
        return Carbon::parse($this->month.'-01')->translatedFormat('F Y');
    }

    /**
     * @return list<array{order_id: int, order_number: string, due_date: string, amount: string|null, currency_code: string|null, customer_name: string|null}>
     */
    #[Computed]
    public function selectedDateRows(): array
    {
        if ($this->selectedDate === null || $this->selectedDate === '') {
            return [];
        }

        return $this->projectionsByDueDate[$this->selectedDate] ?? [];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Payment due calendar')">
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.index')" variant="outline" wire:navigate>{{ __('Finance summary') }}</flux:button>
            <flux:button :href="route('dashboard')" variant="ghost" wire:navigate>{{ __('Back to dashboard') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>{{ __('Operational reference only') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Expected collection dates are estimates from order date and customer payment terms; not legal or accounting advice.') }}
        </flux:callout.text>
    </flux:callout>

    <flux:card class="!p-4">
        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="lg">{{ $this->calendarHeading }}</flux:heading>
            <div class="flex gap-2">
                <flux:button type="button" variant="outline" wire:click="previousMonth">{{ __('Previous month') }}</flux:button>
                <flux:button type="button" variant="outline" wire:click="nextMonth">{{ __('Next month') }}</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400 sm:text-sm">
            <div>{{ __('Mon') }}</div>
            <div>{{ __('Tue') }}</div>
            <div>{{ __('Wed') }}</div>
            <div>{{ __('Thu') }}</div>
            <div>{{ __('Fri') }}</div>
            <div>{{ __('Sat') }}</div>
            <div>{{ __('Sun') }}</div>
        </div>

        <div class="mt-1 grid gap-1">
            @foreach ($this->calendarWeeks as $week)
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($week as $cell)
                        @if ($cell === null)
                            <div class="min-h-16 rounded border border-transparent bg-transparent sm:min-h-20"></div>
                        @else
                            <button
                                type="button"
                                wire:click="selectDate('{{ $cell['date'] }}')"
                                class="min-h-16 rounded border p-1 text-start sm:min-h-20 {{ $selectedDate === $cell['date'] ? 'border-primary bg-card ring-1 ring-primary' : 'border-border bg-card hover:bg-zinc-50 dark:hover:bg-zinc-800' }}"
                            >
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $cell['day'] }}</span>
                                @if ($cell['count'] > 0)
                                    <flux:badge size="sm" class="mt-1">{{ $cell['count'] }}</flux:badge>
                                @endif
                            </button>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
    </flux:card>

    @if ($selectedDate !== null && $selectedDate !== '')
        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ __('Due on :date', ['date' => $selectedDate]) }}</flux:heading>
            @if (count($this->selectedDateRows) === 0)
                <flux:text class="text-sm text-zinc-500">{{ __('No entries for this day.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Order') }}</flux:table.column>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->selectedDateRows as $row)
                            <flux:table.row :key="$row['order_id'].'-'.$row['order_number']">
                                <flux:table.cell>{{ $row['order_number'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['customer_name'] ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['amount'] !== null)
                                        {{ $row['amount'] }} {{ $row['currency_code'] ?? '' }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
