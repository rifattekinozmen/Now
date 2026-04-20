<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? __($title).' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#18181b">


@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
<script>
    (function () {
        var t = localStorage.getItem('now.color-theme');
        if (t && t !== 'default') document.documentElement.setAttribute('data-theme', t);
    })();
</script>
