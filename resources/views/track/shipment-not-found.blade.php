<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Not found') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-zinc-50 p-6 dark:bg-zinc-900">
        <p class="text-center text-zinc-600 dark:text-zinc-400">{{ __('Tracking link is invalid or expired.') }}</p>
    </body>
</html>
