@props([
    'heading' => null,
])

<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-start sm:items-center sm:justify-between']) }}>
    <div class="min-w-0 flex-1">
        @if (is_string($heading) && $heading !== '')
            <flux:heading size="xl">{{ $heading }}</flux:heading>
        @endif
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="flex flex-shrink-0 flex-wrap gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
