@props([
    'label' => null,
])

<div {{ $attributes->class(['flex flex-col gap-3 rounded-lg border border-border-app bg-card p-4']) }}>
    @if (is_string($label) && $label !== '')
        <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-200">{{ $label }}</flux:heading>
    @endif
    {{ $slot }}
</div>
