<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="store-name" content="{{ \App\Support\StoreProfile::name() }}">
        <link rel="icon" href="{{ \App\Support\StoreProfile::imageUrl('favicon') }}">
        @fonts(['inter', 'manrope'])
        @viteReactRefresh
        @vite('resources/admin/app.tsx')
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
