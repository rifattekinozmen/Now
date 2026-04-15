<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" {{ $attributes }}>
    {{-- Küp üst yüzey — fill-current sınıfından renk alır --}}
    <path
        d="M12 2 3 7l9 5 9-5-9-5Z"
        opacity="0.95"
    />

    {{-- Küp kenar çizgileri — fill:none inline override --}}
    <path
        d="M3 7v10l9 5 9-5V7"
        style="fill:none"
        stroke="currentColor"
        stroke-width="1.6"
    />

    {{-- C şekli — fill:none inline override --}}
    <path
        d="M16 9a4 4 0 1 0 0 6"
        style="fill:none"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linecap="round"
    />
</svg>
