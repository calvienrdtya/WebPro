<!DOCTYPE html>
<html lang="id" class="scroll-smooth scroll-pt-20">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', \App\Support\StoreProfile::name().' — Akun Premium Harga Rupiah')</title>
        <meta name="description" content="@yield('description', 'Akun premium AI, streaming, dan tools kreatif dengan harga rupiah.')">
        <link rel="canonical" href="@yield('canonical', url()->current())">

        <meta property="og:site_name" content="{{ \App\Support\StoreProfile::name() }}">
        <meta property="og:locale" content="id_ID">
        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:title" content="@yield('title', \App\Support\StoreProfile::name().' — Akun Premium Harga Rupiah')">
        <meta property="og:description" content="@yield('description', 'Akun premium AI, streaming, dan tools kreatif dengan harga rupiah.')">
        <meta property="og:url" content="@yield('canonical', url()->current())">
        <meta property="og:image" content="@yield('og_image', \App\Support\StoreProfile::imageUrl('og_image'))">
        <meta name="twitter:card" content="summary_large_image">
        @stack('meta')

        <link rel="icon" href="{{ \App\Support\StoreProfile::imageUrl('favicon') }}">
        @fonts('space-grotesk')
        @php($chatConfig = \App\Support\Whatsapp::chatConfig())
        @if ($chatConfig)
            {{-- Disetel sebelum store.js (module, deferred) berjalan; tanpa ini widget chat tidak dimuat. --}}
            <script>window.PROAKSES_CHAT = @json($chatConfig)</script>
        @endif
        @vite(['resources/css/store.css', 'resources/js/store.js'])
    </head>
    <body class="relative min-h-screen overflow-x-hidden bg-white font-sans text-[#374151] antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-full focus:bg-[#0D1428] focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#FDE68A]">
            Lewati ke konten
        </a>

        @include('store.partials.navbar')

        @yield('content')

        @unless (View::hasSection('hide_footer'))
            @include('store.partials.footer')
        @endunless

        @include('store.partials.toast')

        @stack('scripts')
    </body>
</html>
