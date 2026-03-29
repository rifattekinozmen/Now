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
        <div class="flex w-full min-w-0 items-center gap-2 border-b border-border-app bg-card px-3 py-2 lg:px-4">
            <flux:button
                type="button"
                variant="ghost"
                size="sm"
                class="shrink-0"
                wire:click="openSearch"
                icon="magnifying-glass"
            >
                {{ __('Search') }}
            </flux:button>
            <flux:text class="hidden text-xs text-zinc-500 sm:inline dark:text-zinc-400">
                {{ __('Ctrl+K') }}
            </flux:text>
        </div>

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
