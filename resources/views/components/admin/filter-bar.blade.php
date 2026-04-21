{{--
    Liste ve rapor blokları için tek tip kart kabuğu (Flux).
    Eski border-border-app stili yerine flux:card ile admin sayfalarında görsel tutarlılık.
--}}
@props([
    'label' => null,
])

<flux:card {{ $attributes->class(['p-4']) }}>
    @if (is_string($label) && $label !== '')
        <flux:heading size="lg" class="mb-4">{{ $label }}</flux:heading>
    @endif
    {{ $slot }}
</flux:card>
