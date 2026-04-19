<?php

use App\Authorization\LogisticsPermission;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Journal entries')] class extends Component
{
    use WithPagination;

    // Filters
    public string $filterSearch   = '';
    public string $filterDateFrom = '';
    public string $filterDateTo   = '';

    // Form state — null=hidden, 0=new entry, >0=edit
    public ?int $editingId = null;

    public string $entryDate  = '';
    public string $reference  = '';
    public string $memo       = '';

    /**
     * @var list<array{chart_account_id: string, debit: string, credit: string}>
     */
    public array $lines = [];

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
     * @return \Illuminate\Database\Eloquent\Collection<int, ChartAccount>
     */
    #[Computed]
    public function chartAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        return ChartAccount::query()->orderBy('code')->get();
    }

    public function initCreate(): void
    {
        Gate::authorize('create', JournalEntry::class);

        $this->editingId = 0;
        $this->entryDate = now()->format('Y-m-d');
        $this->reference = '';
        $this->memo      = '';
        $this->lines     = [
            ['chart_account_id' => '', 'debit' => '', 'credit' => ''],
            ['chart_account_id' => '', 'debit' => '', 'credit' => ''],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['chart_account_id' => '', 'debit' => '', 'credit' => ''];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 2) {
            return;
        }

        array_splice($this->lines, $index, 1);
        $this->lines = array_values($this->lines);
    }

    public function cancelEntry(): void
    {
        $this->editingId = null;
        $this->lines     = [];
    }

    public function saveEntry(): void
    {
        Gate::authorize('create', JournalEntry::class);

        $this->validate([
            'entryDate'             => ['required', 'date'],
            'reference'             => ['nullable', 'string', 'max:64'],
            'memo'                  => ['nullable', 'string', 'max:500'],
            'lines'                 => ['required', 'array', 'min:2'],
            'lines.*.chart_account_id' => ['required', 'integer', 'exists:chart_accounts,id'],
            'lines.*.debit'         => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit'        => ['nullable', 'numeric', 'min:0'],
        ]);

        $totalDebit  = collect($this->lines)->sum(fn ($l) => (float) ($l['debit'] ?: 0));
        $totalCredit = collect($this->lines)->sum(fn ($l) => (float) ($l['credit'] ?: 0));

        if (abs($totalDebit - $totalCredit) > 0.005) {
            $this->addError('lines', __('Journal entry must be balanced: total debit must equal total credit.'));

            return;
        }

        if ($totalDebit <= 0) {
            $this->addError('lines', __('At least one line must have a non-zero amount.'));

            return;
        }

        DB::transaction(function (): void {
            $entry = JournalEntry::query()->create([
                'entry_date' => $this->entryDate,
                'reference'  => $this->reference ?: null,
                'memo'       => $this->memo ?: null,
                'user_id'    => auth()->id(),
            ]);

            foreach ($this->lines as $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'chart_account_id' => (int) $line['chart_account_id'],
                    'debit'            => filled($line['debit']) ? (float) $line['debit'] : 0,
                    'credit'           => filled($line['credit']) ? (float) $line['credit'] : 0,
                ]);
            }
        });

        $this->cancelEntry();
        $this->resetPage();
        session()->flash('success', __('Journal entry created.'));
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = JournalEntry::query()->findOrFail($entryId);
        Gate::authorize('delete', $entry);

        DB::transaction(function () use ($entry): void {
            $entry->lines()->delete();
            $entry->delete();
        });

        session()->flash('success', __('Journal entry deleted.'));
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

    @php
        $canWrite = auth()->user()?->can(\App\Authorization\LogisticsPermission::FINANCE_WRITE);
    @endphp

    <x-admin.page-header
        :heading="__('Journal entries')"
        :description="__('General ledger double-entry journal.')"
    >
        <x-slot name="actions">
            @if ($canWrite && $editingId === null)
                <flux:button variant="primary" icon="plus" wire:click="initCreate">
                    {{ __('New entry') }}
                </flux:button>
            @endif
            <flux:button :href="route('admin.finance.index')" variant="outline" wire:navigate>
                {{ __('Back to finance') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    @error('lines')
        <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
    @enderror

    {{-- New entry form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-5">
            <flux:heading size="lg" class="mb-4">{{ __('New journal entry') }}</flux:heading>
            <form wire:submit="saveEntry" class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>{{ __('Entry date') }} *</flux:label>
                        <flux:input wire:model="entryDate" type="date" required />
                        <flux:error name="entryDate" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Reference') }}</flux:label>
                        <flux:input wire:model="reference" placeholder="JE-2026-001" />
                        <flux:error name="reference" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Memo') }}</flux:label>
                        <flux:input wire:model="memo" />
                        <flux:error name="memo" />
                    </flux:field>
                </div>

                {{-- Lines --}}
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr class="text-xs text-zinc-500 dark:text-zinc-400">
                                <th class="w-1/2 px-3 py-2 text-start font-medium">{{ __('Account') }}</th>
                                <th class="px-3 py-2 text-end font-medium">{{ __('Debit') }}</th>
                                <th class="px-3 py-2 text-end font-medium">{{ __('Credit') }}</th>
                                <th class="w-10 px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($lines as $i => $line)
                                <tr wire:key="line-{{ $i }}">
                                    <td class="px-3 py-2">
                                        <flux:select wire:model="lines.{{ $i }}.chart_account_id" class="w-full">
                                            <option value="">— {{ __('Select account') }} —</option>
                                            @foreach ($this->chartAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="lines.{{ $i }}.chart_account_id" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <flux:input
                                            wire:model="lines.{{ $i }}.debit"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            class="w-32 text-end"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <flux:input
                                            wire:model="lines.{{ $i }}.credit"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            class="w-32 text-end"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        @if (count($lines) > 2)
                                            <flux:button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                icon="x-mark"
                                                wire:click="removeLine({{ $i }})"
                                            />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-zinc-200 dark:border-zinc-700">
                            <tr class="bg-zinc-50 dark:bg-zinc-800/50 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                                <td class="px-3 py-2">{{ __('Totals') }}</td>
                                <td class="px-3 py-2 text-end font-mono">
                                    {{ number_format(collect($lines)->sum(fn ($l) => (float) ($l['debit'] ?: 0)), 2) }}
                                </td>
                                <td class="px-3 py-2 text-end font-mono">
                                    {{ number_format(collect($lines)->sum(fn ($l) => (float) ($l['credit'] ?: 0)), 2) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button type="button" variant="ghost" size="sm" icon="plus" wire:click="addLine">
                        {{ __('Add line') }}
                    </flux:button>
                    <div class="flex-1"></div>
                    <flux:button type="button" variant="ghost" wire:click="cancelEntry">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save entry') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

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
                            @if ($canWrite && ! $entry->source_type)
                                <div class="ml-auto">
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="danger"
                                        wire:click="deleteEntry({{ $entry->id }})"
                                        wire:confirm="{{ __('Delete this journal entry and all its lines?') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>
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
