<div
    class="contents"
    x-data
    @keydown.window="
        if (($event.ctrlKey || $event.metaKey) && ($event.key === 'k' || $event.key === 'K')) {
            $event.preventDefault();
            $wire.openSearch();
        }
        if ($event.key === 'Escape' && $wire.open) {
            $wire.closeSearch();
        }
    "
>
    @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])
        <button
            type="button"
            wire:click="openSearch"
            class="flex h-8 w-56 items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-sm text-zinc-500 transition hover:border-zinc-300 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:border-zinc-600 dark:hover:bg-zinc-700"
        >
            <svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
            <span class="flex-1 text-left">{{ __('Search…') }}</span>
            <kbd class="hidden rounded border border-zinc-300 bg-white px-1.5 py-0.5 text-[10px] font-medium text-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-400 sm:inline">Ctrl+K</kbd>
        </button>

        @if ($open)
            <div
                class="fixed inset-0 z-50 flex items-start justify-center bg-zinc-950/40 p-4 pt-[10vh]"
                wire:click="closeSearch"
                wire:key="global-search-overlay"
            >
                <div
                    class="w-full max-w-lg rounded-lg border border-border-app bg-card p-4 shadow-sm dark:bg-zinc-900"
                    wire:click.stop
                >
                    <flux:heading size="lg" class="mb-3">{{ __('Quick search') }}</flux:heading>
                    <flux:input
                        wire:model.live.debounce.300ms="q"
                        :label="__('Type at least 2 characters')"
                        placeholder="{{ __('Plate, order no, customer, shipment…') }}"
                        autofocus
                    />

                    <ul class="mt-4 max-h-80 space-y-1 overflow-y-auto text-sm">
                        @forelse ($this->results as $row)
                            <li>
                                <a
                                    href="{{ $row['url'] }}"
                                    wire:navigate
                                    @click="$wire.closeSearch()"
                                    class="block rounded-md px-2 py-2 text-zinc-800 hover:bg-zinc-100 dark:text-zinc-100 dark:hover:bg-zinc-800"
                                >
                                    <span class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ $row['kind'] }}</span>
                                    <span class="ms-2">{{ $row['label'] }}</span>
                                </a>
                            </li>
                        @empty
                            <li class="px-2 py-4 text-center text-zinc-500">
                                @if (strlen(trim($q)) < 2)
                                    {{ __('Enter a search term.') }}
                                @else
                                    {{ __('No results.') }}
                                @endif
                            </li>
                        @endforelse
                    </ul>

                    <div class="mt-4 flex justify-end">
                        <flux:button type="button" variant="ghost" size="sm" wire:click="closeSearch">{{ __('Close') }}</flux:button>
                    </div>
                </div>
            </div>
        @endif
    @endcanany
</div>
