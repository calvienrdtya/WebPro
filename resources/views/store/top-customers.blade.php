@extends('layouts.store')

@php
    $labels = $periods;
    $initials = fn (string $name) => collect(preg_split('/[\s.]+/', $name, -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $levelName = ['legend' => 'Legenda', 'elite' => 'Elite', 'loyal' => 'Setia', 'member' => 'Anggota'];
    $levelIcon = [
        'legend' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 8l4.500 4L12 5l4.500 7L21 8l-2 11H5L3 8Z"/></svg>',
        'elite' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 3h12l4 6-10 12L2 9l4-6Z"/></svg>',
        'loyal' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.900 6.600 7.100.6-5.400 4.700 1.600 7-6.200-3.700-6.200 3.700 1.600-7L2 9.200l7.100-.6L12 2Z"/></svg>',
        'member' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    ];
    $iconBag = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
    $crown = '<svg class="crown" viewBox="0 0 48 48" aria-hidden="true"><path d="M6 15l9 8 9-14 9 14 9-8-4 22H10L6 15Z" fill="url(#gGold)" stroke="#B45309" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 37h28v5a1.5 1.5 0 0 1-1.500 1.500h-25A1.5 1.5 0 0 1 10 42v-5Z" fill="url(#gGoldDark)"/><circle cx="6" cy="14" r="2.6" fill="#FEF3C7"/><circle cx="24" cy="8" r="2.8" fill="#FEF3C7"/><circle cx="42" cy="14" r="2.6" fill="#FEF3C7"/><path d="M14 30h20" stroke="#fff" stroke-opacity=".45" stroke-width="2" stroke-linecap="round"/></svg>';
    $medal = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="15" r="6"/><path d="m8.500 3 3.500 6 3.500-6"/></svg>';
    $podium = array_slice($ranking, 0, 3);
    $rest = array_slice($ranking, 3);
    $top = $ranking[0]['orders'] ?? 1;
    $rp = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.');
@endphp

@section('title', 'Top Pelanggan '.\App\Support\StoreProfile::name().' — Pelanggan Terbaik Kami')
@section('description', 'Peringkat pelanggan terbaik ProAkses berdasarkan jumlah pesanan. Apresiasi untuk pelanggan yang paling setia memilih akun premium kami.')
@section('canonical', route('top-customers'))

@if ($period !== 'bulan')
    @push('meta')
        <meta name="robots" content="noindex, follow">
    @endpush
@endif

@section('content')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="gGold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FEF3C7"/><stop offset=".35" stop-color="#FBBF24"/><stop offset=".7" stop-color="#F59E0B"/><stop offset="1" stop-color="#B45309"/></linearGradient>
            <linearGradient id="gGoldDark" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F59E0B"/><stop offset="1" stop-color="#92400E"/></linearGradient>
        </defs>
    </svg>

    <main id="main" class="tp-page">
        {{-- Hero + tab periode + podium --}}
        <section class="stage" aria-labelledby="hero-title">
            <div class="stage-glow"></div>
            <div class="spot" aria-hidden="true"></div>
            <div id="stars" aria-hidden="true"></div>
            <div class="stage-noise" aria-hidden="true"></div>
            <div id="confetti" aria-hidden="true"></div>

            <div class="relative mx-auto max-w-6xl px-5 pt-12 sm:px-8 sm:pt-16">
                <div class="text-center">
                    @include('store.partials.tp-icon', ['name' => 'trophy'])
                    <h1 id="hero-title" class="hero-title reveal mt-4 text-5xl sm:text-6xl lg:text-7xl" style="--d:.15s">Top Pelanggan</h1>
                    <p class="reveal mx-auto mt-4 max-w-xl text-base text-white/75 sm:text-lg" style="--d:.25s">Apresiasi untuk pelanggan terbaik kami, mereka yang paling setia memilih ProAkses.</p>
                    <div class="reveal mt-6 flex justify-center" style="--d:.35s">
                        <p class="live" role="status"><i aria-hidden="true"></i><span id="live-text" data-generated="{{ $generatedAt }}">Diperbarui baru saja</span></p>
                    </div>
                </div>

                <div class="mx-auto mt-8 grid max-w-3xl gap-3 sm:grid-cols-3 sm:gap-4">
                    <div class="hstat reveal" style="--d:.4s">
                        <p class="hstat-label">@include('store.partials.tp-icon', ['name' => 'stat0'])Total Pelanggan</p>
                        <p class="hstat-value" data-count="{{ $stats['customers'] }}">{{ number_format($stats['customers'], 0, ',', '.') }}</p>
                    </div>
                    <div class="hstat reveal" style="--d:.5s">
                        <p class="hstat-label">@include('store.partials.tp-icon', ['name' => 'stat1'])Total Transaksi</p>
                        <p class="hstat-value" data-count="{{ $stats['orders'] }}">{{ number_format($stats['orders'], 0, ',', '.') }}</p>
                    </div>
                    <div class="hstat reveal" style="--d:.6s">
                        <p class="hstat-label">@include('store.partials.tp-icon', ['name' => 'stat2'])Bulan Berjalan</p>
                        <p class="hstat-value" style="font-size:1.5rem">{{ now()->translatedFormat('F Y') }}</p>
                    </div>
                </div>

                <div class="reveal mt-10 flex justify-center" style="--d:.7s">
                    <nav id="seg" aria-label="Periode peringkat" class="seg">
                        <span class="seg-ind" id="seg-ind" aria-hidden="true"></span>
                        @foreach (['minggu' => ['Minggu Ini', 'week'], 'bulan' => ['Bulan Ini', 'month'], 'semua' => ['Sepanjang Waktu', 'all']] as $key => [$label, $iconKey])
                            <a href="{{ route('top-customers', $key === 'bulan' ? [] : ['periode' => $key]) }}" id="tab-{{ $key }}" class="seg-btn" aria-selected="{{ $period === $key ? 'true' : 'false' }}" @if ($period === $key) aria-current="page" @endif>
                                @include('store.partials.tp-icon', ['name' => 'seg-'.$iconKey])
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                <h2 class="sr-only">Podium 3 pelanggan teratas</h2>
                <div id="podium" class="podium mt-10 pb-8 md:mt-14 md:pb-0">
                    @foreach ($podium as $i => $c)
                        @php $rank = $i + 1; @endphp
                        <article class="pod pod-{{ $rank }}" aria-label="Peringkat {{ $rank }}: {{ $c['name'] }}">
                            <div class="pod-avatar">
                                @if ($rank === 1) {!! $crown !!} @endif
                                <div class="ring"><span class="avatar" role="img" aria-label="Avatar {{ $c['name'] }}">{{ $initials($c['name']) }}</span></div>
                            </div>
                            <div class="pod-info">
                                <span class="pod-tag">{!! $medal !!}JUARA {{ $rank }}</span>
                                <h3 class="pod-name">{{ $c['name'] }}</h3>
                                <p class="pod-orders">{!! $iconBag !!}<span><b data-count="{{ $c['orders'] }}">{{ number_format($c['orders'], 0, ',', '.') }}</b> order</span></p>
                                <p class="pod-spend">Total belanja <span data-count="{{ $c['spend'] }}" data-fmt="rp">{{ $rp($c['spend']) }}</span></p>
                            </div>
                            <div class="platform" aria-hidden="true">{{ $rank }}</div>
                        </article>
                    @endforeach
                </div>

                @if ($ranking === [])
                    <p class="mx-auto mt-2 max-w-md pb-12 text-center text-sm text-white/70">Belum ada peringkat {{ $labels[$period] }}. Jadilah yang pertama dengan berbelanja sekarang.</p>
                @endif
            </div>
        </section>

        {{-- Peringkat 4 dan seterusnya --}}
        <section class="mx-auto max-w-6xl px-5 pt-14 sm:px-8 sm:pt-16" aria-labelledby="board-title">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <h2 id="board-title" class="display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Peringkat Berikutnya</h2>
                    <p class="mt-1.5 text-sm text-slate-500">Peringkat 4 dan seterusnya, {{ $labels[$period] }}.</p>
                </div>
            </div>
            <div id="board" class="lb-list">
                @forelse ($rest as $i => $c)
                    @php
                        $rank = $i + 4;
                        $pct = max(4, (int) round($c['orders'] / $top * 100));
                    @endphp
                    <article class="lb-row reveal {{ $rank <= 10 ? 'lb-row--top' : '' }}" style="--d:{{ number_format(0.05 * min($i, 12), 2) }}s" aria-label="Peringkat {{ $rank }}: {{ $c['name'] }}" title="{{ $pct }}% menuju peringkat 1">
                        <span class="lb-rank">{{ $rank }}</span>
                        <div class="lb-who">
                            <span class="lb-avatar" role="img" aria-label="Avatar {{ $c['name'] }}">{{ $initials($c['name']) }}</span>
                            <div class="min-w-0">
                                <p class="lb-name">{{ $c['name'] }}</p>
                                <span class="lvl lvl--{{ $c['level'] }}">{!! $levelIcon[$c['level']] !!}{{ $levelName[$c['level']] }}</span>
                            </div>
                        </div>
                        <div class="lb-nums">
                            <p class="lb-order"><b data-count="{{ $c['orders'] }}">{{ number_format($c['orders'], 0, ',', '.') }}</b> <span>order</span></p>
                            <p class="lb-spend" data-count="{{ $c['spend'] }}" data-fmt="rp">{{ $rp($c['spend']) }}</p>
                        </div>
                        <span class="lb-prog" aria-hidden="true"><i data-w="{{ $pct }}"></i></span>
                    </article>
                @empty
                    <p class="col-span-full rounded-2xl border border-slate-100 p-8 text-center text-sm text-slate-500">Belum ada peringkat lain untuk periode ini.</p>
                @endforelse
            </div>
            <p class="mt-6 text-center">
                <a href="{{ route('products.index') }}" class="lb-link">Lihat semua produk <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            </p>
        </section>

        {{-- Ajakan belanja --}}
        <section class="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-16" aria-labelledby="cta-title">
            <div class="cta-card px-6 py-12 text-center sm:px-12 sm:py-16">
                <div class="relative">
                    <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#FBBF24]/15 ring-1 ring-[#FBBF24]/40">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#FBBF24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.7V17c0 .6-.5 1-1.2 1.2C7.800 18.700 7 20.200 7 22M14 14.7V17c0 .6.5 1 1.200 1.200 1 .5 1.800 2 1.800 3.800M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                    </span>
                    <h2 id="cta-title" class="display mx-auto mt-6 max-w-xl text-3xl font-extrabold tracking-tight sm:text-4xl">Ingin masuk daftar <span class="text-[#FBBF24]">Top Pelanggan</span>?</h2>
                    <p class="mx-auto mt-4 max-w-lg text-base text-white/75">Peringkat dihitung dari jumlah pesanan yang sudah lunas. Belanja lebih sering dan naiklah ke podium.</p>
                    <a href="{{ route('products.index') }}" class="cta-btn mt-8">Mulai Belanja</a>
                </div>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        (function () {
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
            var num = function (n) { return n.toLocaleString('id-ID') }
            var fmt = function (el, n) { return el.getAttribute('data-fmt') === 'rp' ? 'Rp' + num(n) : num(n) }

            // Angka naik dari 0 saat halaman dibuka.
            document.querySelectorAll('[data-count]').forEach(function (el, i) {
                var target = Number(el.getAttribute('data-count'))
                if (reduce) return
                el.textContent = fmt(el, 0)
                var start = null
                setTimeout(function () {
                    requestAnimationFrame(function step(ts) {
                        if (start === null) start = ts
                        var p = Math.min(1, (ts - start) / 1100)
                        el.textContent = fmt(el, Math.round(target * (1 - Math.pow(1 - p, 3))))
                        if (p < 1) requestAnimationFrame(step)
                    })
                }, 300 + Math.min(i, 8) * 40)
            })

            // Bilah kemajuan menuju peringkat 1.
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    document.querySelectorAll('.lb-prog i').forEach(function (b) { b.style.width = b.getAttribute('data-w') + '%' })
                })
            })

            // Teks "Diperbarui ... lalu" mengikuti waktu data dibuat di server.
            var live = document.getElementById('live-text')
            function updateLive() {
                var m = Math.max(0, Math.floor((Date.now() / 1000 - Number(live.getAttribute('data-generated'))) / 60))
                live.textContent = m < 1 ? 'Diperbarui baru saja' : 'Diperbarui ' + m + ' menit lalu'
            }
            updateLive()
            setInterval(updateLive, 30000)

            // Indikator tab periode.
            var ind = document.getElementById('seg-ind')
            function moveInd() {
                var active = document.querySelector('#seg [aria-selected=true]')
                if (!active) return
                ind.style.width = active.offsetWidth + 'px'
                ind.style.transform = 'translateX(' + active.offsetLeft + 'px)'
            }
            ind.style.transition = 'none'
            moveInd()
            requestAnimationFrame(function () { ind.style.transition = '' })
            window.addEventListener('resize', moveInd)
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { ind.style.transition = 'none'; moveInd(); requestAnimationFrame(function () { ind.style.transition = '' }) })

            // Bintang jatuh halus dan confetti sekali saat dibuka.
            if (reduce) return
            var stars = document.getElementById('stars')
            for (var i = 0; i < 12; i++) {
                var m = document.createElement('span')
                m.className = 'meteor'
                m.style.left = (Math.random() * 100) + '%'
                m.style.height = (10 + Math.random() * 16) + 'px'
                m.style.setProperty('--dur', (7 + Math.random() * 7).toFixed(1) + 's')
                m.style.setProperty('--dl', (Math.random() * 9).toFixed(1) + 's')
                stars.appendChild(m)
            }
            var conf = document.getElementById('confetti')
            var colors = ['#FBBF24', '#F59E0B', '#FDE68A', '#ffffff', '#FCD9A8']
            for (var j = 0; j < (window.innerWidth < 640 ? 60 : 130); j++) {
                var p = document.createElement('span')
                p.className = 'piece'
                p.style.left = (Math.random() * 100) + '%'
                p.style.top = (8 + Math.random() * 55) + '%'
                p.style.setProperty('--fall', (140 + Math.random() * 200).toFixed(0) + 'px')
                p.style.width = (5 + Math.random() * 6) + 'px'
                p.style.height = (8 + Math.random() * 8) + 'px'
                p.style.background = colors[j % colors.length]
                p.style.setProperty('--dx', ((Math.random() - .5) * 200).toFixed(0) + 'px')
                p.style.setProperty('--rot', ((Math.random() - .5) * 900).toFixed(0) + 'deg')
                p.style.setProperty('--dur', (2.6 + Math.random() * 2).toFixed(1) + 's')
                p.style.setProperty('--dl', (0.7 + Math.random() * 2.2).toFixed(2) + 's')
                conf.appendChild(p)
            }
            setTimeout(function () { conf.remove() }, 8500)
        })()
    </script>
@endpush
