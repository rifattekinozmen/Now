@props([
    'sidebar' => false,
])

@php
    $brandName  = auth()->user()?->tenant?->name ?? 'Now';
    $tid        = auth()->check() ? (int) auth()->user()->tenant_id : null;
    $logoPath   = $tid ? \App\Models\TenantSetting::get($tid, 'company_logo') : null;
    $logoUrl    = $logoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) : null;
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="size-full object-contain" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="size-full object-contain" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
