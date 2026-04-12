<?php

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Journal entries')] class extends Component
{
    use WithPagination;

    // Filters
    public string $filterSearch  = '';
    public string $filterDateFrom = '';
    public string $filterDateTo   = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', JournalEntry::class);
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->filterSearch   = '';
        $this->filterDateFrom = '';
        $this->filterDateTo   = '';
        $this->resetPage();
    }

    /**
     * @return array{total:int, this_month:int, this_year:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $now = now();

        return [
            'total'      => JournalEntry::query()->count(),
            'this_month' => JournalEntry::query()
                ->whereYear('entry_date', $now->year)
                ->whereMonth('entry_date', $now->month)
                ->count(),
            'this_year'  => JournalEntry::query()
                ->whereYear('entry_date', $now->year)
                ->count(),
        ];
    }

    private function entryQuery(): Builder
    {
        $q = JournalEntry::query()->with(['lines.chartAccount', 'user']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $inner) use ($term): void {
                $inner->where('reference', 'like', $term)
                    ->orWhere('memo', 'like', $term);
            });
        }

        if ($this->filterDateFrom !== '') {
            $q->where('entry_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $q->where('entry_date', '<=', $this->filterDateTo);
        }

        return $q->orderByDesc('entry_date')->orderByDesc('id');
    }

    #[Computed]
    public function paginatedEntries(): LengthAwarePaginator
    {
        return $this->entryQuery()->paginate(20);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Journal entries')"
        :description="__('General ledger double-entry journal.')"
    >
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.index')" variant="outline" wire:navigate>
                {{ __('Back to finance') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total entries') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This month') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['this_month'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This year') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['this_year'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live="filterSearch" :label="__('Search')" :placeholder="__('Reference or memo…')" class="max-w-[240px]" />
        <flux:input wire:model.live="filterDateFrom" type="date" :label="__('From')" class="max-w-[160px]" />
        <flux:input wire:model.live="filterDateTo" type="date" :label="__('To')" class="max-w-[160px]" />
        @if ($filterSearch !== '' || $filterDateFrom !== '' || $filterDateTo !== '')
            <div class="flex items-end">
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                    {{ __('Clear') }}
                </flux:button>
            </div>
        @endif
    </div>

    {{-- Entries --}}
    <flux:card class="p-4">
        @if ($this->paginatedEntries->isEmpty())
            <flux:text class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No journal entries found.') }}
            </flux:text>
        @else
            <div class="space-y-6">
                @foreach ($this->paginatedEntries as $entry)
                    <div
                        id="entry-{{ $entry->id }}"
                        class="scroll-mt-24 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"
                        wire:key="je-{{ $entry->id }}"
                    >
                        {{-- Entry header --}}
                        <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span class="font-mono font-semibold text-zinc-700 dark:text-zinc-200">#{{ $entry->id }}</span>
                            <span class="text-zinc-500 dark:text-zinc-400">{{ $entry->entry_date?->format('d M Y') }}</span>
                            @if ($entry->reference)
                                <flux:badge color="blue" size="sm">{{ $entry->reference }}</flux:badge>
                            @endif
                            @if ($entry->source_type)
                                <flux:badge color="zinc" size="sm">{{ $entry->source_type }}</flux:badge>
                            @endif
                            @if ($entry->user)
                                <span class="text-zinc-400 dark:text-zinc-500">{{ $entry->user->name }}</span>
                            @endif
                        </div>
                        @if ($entry->memo)
                            <flux:text class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $entry->memo }}</flux:text>
                        @endif

                        {{-- Lines table --}}
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                                <thead>
                                    <tr class="text-xs text-zinc-400 dark:text-zinc-500">
                                        <th class="py-1.5 pe-3 text-start font-medium">{{ __('Account') }}</th>
                                        <th class="py-1.5 pe-3 text-end font-medium">{{ __('Debit') }}</th>
                                        <th class="py-1.5 text-end font-medium">{{ __('Credit') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-900">
                                    @foreach ($entry->lines as $line)
                                        <tr wire:key="jl-{{ $line->id }}">
                                            <td class="py-1.5 pe-3">
                                                @if ($line->chartAccount)
                                                    <span class="font-mono text-xs text-zinc-500">{{ $line->chartAccount->code }}</span>
                                                    <span class="ms-1 text-zinc-700 dark:text-zinc-300">{{ $line->chartAccount->name }}</span>
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </td>
                                            <td class="py-1.5 pe-3 text-end font-mono">
                                                @if ($line->debit > 0)
                                                    <span class="text-zinc-800 dark:text-zinc-200">{{ number_format($line->debit, 2) }}</span>
                                                @else
                                                    <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                                @endif
                                            </td>
                                            <td class="py-1.5 text-end font-mono">
                                                @if ($line->credit > 0)
                                                    <span class="text-zinc-800 dark:text-zinc-200">{{ number_format($line->credit, 2) }}</span>
                                                @else
                                                    <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="py-1.5 pe-3 text-xs text-zinc-400">{{ __(':count lines', ['count' => $entry->lines->count()]) }}</td>
                                        <td class="py-1.5 pe-3 text-end font-mono text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                                            {{ number_format($entry->lines->sum('debit'), 2) }}
                                        </td>
                                        <td class="py-1.5 text-end font-mono text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                                            {{ number_format($entry->lines->sum('credit'), 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $this->paginatedEntries->links() }}</div>
        @endif
    </flux:card>
</div>
