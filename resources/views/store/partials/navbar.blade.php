@php
    // [label => [url, pola rute yang menandai menu ini aktif]]. "Cara Order" hanya anchor di beranda, jadi tidak pernah aktif.
    $links = [
        'Produk' => [route('products.index'), ['products.*']],
        'Cara Order' => [route('home').'#cara-order', []],
        'Top Pelanggan' => [route('top-customers'), ['top-customers']],
        'Voucher' => [route('vouchers.index'), ['vouchers.*']],
        'FAQ' => [route('faq'), ['faq']],
    ];
    $btnGold = 'btn-shine rounded-2xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-5 py-2 text-sm font-semibold text-[#0A0F1F] shadow-md shadow-[#F59E0B]/30 transition hover:brightness-105 active:scale-[0.99]';
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-slate-100 bg-white/80 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
        <a href="{{ route('home') }}">
            <img src="{{ \App\Support\StoreProfile::imageUrl('logo') }}" alt="{{ \App\Support\StoreProfile::name() }}" {!! \App\Support\StoreProfile::imageSize('logo') !!} class="h-14 w-auto max-w-[240px] object-contain sm:h-16 sm:max-w-[320px]" />
        </a>

        <nav class="hidden items-center gap-1 md:flex">
            @foreach ($links as $label => [$href, $patterns])
                @php($active = $patterns && request()->routeIs(...$patterns))
                <a href="{{ $href }}" @if ($active) aria-current="page" @endif
                    class="rounded-full px-4 py-2 text-sm transition {{ $active ? 'nav-active' : 'font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-2 md:flex">
            @guest
                <a href="{{ route('login') }}" class="{{ $btnGold }}">Login</a>
            @endguest
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Keluar</button>
                </form>
            @endauth
            <a href="{{ route('profil') }}" aria-label="Profil" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0" /><circle cx="12" cy="7" r="4" /></svg>
            </a>
        </div>

        <button type="button" @click="open = !open" :aria-expanded="open" :aria-label="open ? 'Tutup menu' : 'Buka menu'" aria-controls="mobile-panel"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-700 transition hover:bg-slate-100 md:hidden">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="4" y1="7" x2="20" y2="7" /><line x1="4" y1="12" x2="20" y2="12" /><line x1="4" y1="17" x2="20" y2="17" />
            </svg>
        </button>
    </div>

    <div id="mobile-panel" x-show="open" x-cloak x-transition class="absolute inset-x-0 top-full z-40 border-b border-t border-slate-100 bg-white px-5 py-4 shadow-lg md:hidden">
        <nav class="flex flex-col">
            @foreach ($links as $label => [$href, $patterns])
                @php($active = $patterns && request()->routeIs(...$patterns))
                <a href="{{ $href }}" @if ($active) aria-current="page" @endif
                    class="border-b border-slate-100 py-3 text-sm {{ $active ? 'font-semibold text-[#F59E0B]' : 'font-medium text-slate-700' }}">{{ $label }}</a>
            @endforeach
            <div class="mt-4 flex flex-col gap-2">
                @guest
                    <a href="{{ route('register') }}" class="rounded-2xl border-2 border-slate-100 bg-slate-50 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:border-slate-200 hover:bg-slate-100">Daftar</a>
                    <a href="{{ route('login') }}" class="{{ $btnGold }} text-center">Login</a>
                @endguest
                @auth
                    <a href="{{ route('profil') }}" class="rounded-2xl border-2 border-slate-100 bg-slate-50 px-4 py-2.5 text-center text-sm font-semibold text-slate-700">Profil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full {{ $btnGold }}">Keluar</button>
                    </form>
                @endauth
            </div>
        </nav>
    </div>
</header>
