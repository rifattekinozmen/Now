<?php

use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Services\Logistics\DriverScorecardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Driver leaderboard')] class extends Component
{
    use RequiresLogisticsAdmin;

    public string $selectedMonth = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', \App\Models\Employee::class);
        $this->selectedMonth = now()->format('Y-m');
    }

    public function updatedSelectedMonth(): void
    {
        unset($this->leaderboard);
    }

    /**
     * @return Collection<int, array{employee_id: int, name: string, score: int, deliveries: int, on_time: int, badge: string}>
     */
    #[Computed]
    public function leaderboard(): Collection
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $month    = Carbon::parse($this->selectedMonth.'-01');

        return app(DriverScorecardService::class)->monthlyLeaderboard($tenantId, $month);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $month = \Illuminate\Support\Carbon::parse($selectedMonth.'-01');
    @endphp
    <x-admin.page-header
        :heading="__('Driver leaderboard')"
        :description="__('Monthly delivery scores and on-time performance by driver.')"
    >
        <x-slot name="actions">
            <flux:input wire:model.live="selectedMonth" type="month" class="w-44" :label="__('Month')" />
        </x-slot>
    </x-admin.page-header>

    <p class="text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Period: :month', ['month' => $month->translatedFormat('F Y')]) }}
    </p>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-3 text-sm">
        <span class="flex items-center gap-1.5">
            <span class="inline-block size-3 rounded-full bg-yellow-400"></span> {{ __('Gold') }} (90+)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block size-3 rounded-full bg-zinc-400"></span> {{ __('Silver') }} (70–89)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block size-3 rounded-full bg-orange-400"></span> {{ __('Bronze') }} (50–69)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block size-3 rounded-full bg-zinc-200 dark:bg-zinc-700"></span> {{ __('None') }} (&lt;50)
        </span>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>#</flux:table.column>
                <flux:table.column>{{ __('Driver') }}</flux:table.column>
                <flux:table.column>{{ __('Badge') }}</flux:table.column>
                <flux:table.column>{{ __('Score') }}</flux:table.column>
                <flux:table.column>{{ __('Deliveries') }}</flux:table.column>
                <flux:table.column>{{ __('On Time') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->leaderboard as $rank => $row)
                    <flux:table.row :key="$row['employee_id']">
                        <flux:table.cell>
                            <span @class([
                                'text-lg font-bold',
                                'text-yellow-500' => $rank === 0,
                                'text-zinc-400'   => $rank === 1,
                                'text-orange-400' => $rank === 2,
                                'text-zinc-500'   => $rank > 2,
                            ])>
                                #{{ $rank + 1 }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('admin.employees.show', $row['employee_id']) }}" wire:navigate
                               class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                {{ $row['name'] }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php $badgeColor = match($row['badge']) {
                                'gold'   => 'yellow',
                                'silver' => 'zinc',
                                'bronze' => 'orange',
                                default  => 'zinc',
                            }; @endphp
                            @if ($row['badge'] !== 'none')
                                <flux:badge :color="$badgeColor" size="sm">{{ ucfirst($row['badge']) }}</flux:badge>
                            @else
                                <span class="text-sm text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div
                                        class="h-full rounded-full bg-blue-500"
                                        style="width: {{ min($row['score'], 100) }}%"
                                    ></div>
                                </div>
                                <span class="text-sm font-semibold">{{ $row['score'] }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['deliveries'] }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($row['deliveries'] > 0)
                                <span @class(['text-green-600' => $row['on_time'] === $row['deliveries'], 'text-yellow-600' => $row['on_time'] < $row['deliveries']])>
                                    {{ $row['on_time'] }} / {{ $row['deliveries'] }}
                                </span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            {{ __('No drivers found for this period.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
