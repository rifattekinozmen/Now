{{--
    Standart liste (index) üst aksiyon düzeni: solda Geri (opsiyonel), sağda sırayla
    Yazdır → Dışa Aktar → İçe Aktar → Birincil CTA (ör. Yeni Ekle).
    Kullanım: x-admin.page-header içindeki actions slotunda.
--}}
@props([
    'fullWidth' => true,
])

@php
    $wrapClass = $fullWidth
        ? 'w-full min-w-0 flex flex-wrap items-center gap-2'
        : 'flex flex-wrap items-center gap-2';
@endphp

<div {{ $attributes->class([$wrapClass]) }}>
    @isset($back)
        @if (! $back->isEmpty())
            <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                {{ $back }}
            </div>
        @endif
    @endisset

    <div @class([
        'ms-auto flex min-w-0 flex-wrap items-center justify-end gap-2',
        'w-full sm:w-auto' => $fullWidth,
    ])>
        @isset($extra)
            @if (! $extra->isEmpty())
                {{ $extra }}
            @endif
        @endisset

        @isset($print)
            @if (! $print->isEmpty())
                {{ $print }}
            @endif
        @endisset

        @isset($export)
            @if (! $export->isEmpty())
                {{ $export }}
            @endif
        @endisset

        @isset($import)
            @if (! $import->isEmpty())
                {{ $import }}
            @endif
        @endisset

        @isset($primary)
            @if (! $primary->isEmpty())
                {{ $primary }}
            @endif
        @endisset
    </div>
</div>
