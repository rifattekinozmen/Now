<?php

use App\Models\JournalEntry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Journal entries')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', JournalEntry::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, JournalEntry>
     */
    public function getEntriesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return JournalEntry::query()
            ->with(['lines.chartAccount'])
            ->latest('entry_date')
            ->latest('id')
            ->limit(100)
            ->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Journal entries') }}</flux:heading>
        <flux:button :href="route('admin.finance.index')" variant="ghost" wire:navigate>{{ __('Back to finance summary') }}</flux:button>
    </div>

    <flux:card>
        @if ($this->entries->isEmpty())
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No journal entries yet.') }}</flux:text>
        @else
            <div class="space-y-8">
                @foreach ($this->entries as $entry)
                    <div id="entry-{{ $entry->id }}" class="border-b border-border pb-6 scroll-mt-24 last:border-0 last:pb-0" wire:key="je-{{ $entry->id }}">
                        <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                            <span>#{{ $entry->id }}</span>
                            <span>{{ $entry->entry_date?->format('Y-m-d') }}</span>
                            @if ($entry->reference)
                                <span>{{ $entry->reference }}</span>
                            @endif
                        </div>
                        @if ($entry->memo)
                            <flux:text class="mb-2 text-sm">{{ $entry->memo }}</flux:text>
                        @endif
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Account') }}</flux:table.column>
                                <flux:table.column>{{ __('Debit') }}</flux:table.column>
                                <flux:table.column>{{ __('Credit') }}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($entry->lines as $line)
                                    <flux:table.row :key="$line->id">
                                        <flux:table.cell>
                                            {{ $line->chartAccount?->code }}
                                            —
                                            {{ $line->chartAccount?->name }}
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $line->debit }}</flux:table.cell>
                                        <flux:table.cell>{{ $line->credit }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>
</div>
