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
    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
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
            <div class="flex w-full min-w-0 flex-shrink-0 flex-wrap items-center justify-stretch gap-2 sm:w-auto sm:justify-end">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
