@props([
    'heading' => null,
    'description' => null,
])

<div {{ $attributes->class(['flex flex-col gap-3']) }}>
    @isset($breadcrumb)
        <nav class="text-sm text-zinc-500 dark:text-zinc-400" aria-label="{{ __('Breadcrumb') }}">
            {{ $breadcrumb }}
        </nav>
    @endisset
    {{--
        Grid: sol başlık (küçülebilir), sağ aksiyonlar — tek satırda; dikeyde başlık bloğu ile ortalanır.
    --}}
    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2">
        <div class="min-w-0 max-w-full">
            @if (is_string($heading) && $heading !== '')
                <flux:heading size="xl" class="break-words">{{ $heading }}</flux:heading>
            @endif
        </div>
        @isset($actions)
            <div class="flex min-w-0 shrink-0 flex-wrap items-center justify-end gap-2 justify-self-end">
                {{ $actions }}
            </div>
        @endisset
    </div>
    @if (is_string($description) && $description !== '')
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $description }}</flux:text>
    @endif
    @isset($slot)
        @if (! $slot->isEmpty())
            <div class="min-w-0">
                {{ $slot }}
            </div>
        @endif
    @endisset
</div>
