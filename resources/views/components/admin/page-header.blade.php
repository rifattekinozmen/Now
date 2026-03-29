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
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            @if (is_string($heading) && $heading !== '')
                <flux:heading size="xl">{{ $heading }}</flux:heading>
            @endif
            @if (is_string($description) && $description !== '')
                <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $description }}</flux:text>
            @endif
            {{ $slot }}
        </div>
        @isset($actions)
            <div class="flex flex-shrink-0 flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
