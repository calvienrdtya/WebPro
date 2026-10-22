@extends('layouts.store')

@section('title', \App\Support\StoreProfile::name().' — Akun Premium Harga Rupiah')
@section('description', 'ProAkses menjual akun premium ChatGPT Plus, Claude Pro, Netflix, Spotify, dan lainnya dengan harga rupiah, tanpa kartu kredit, dikirim setelah pembayaran terverifikasi.')

@php
    $wa = \App\Support\Whatsapp::url();
    $store = \App\Support\StoreProfile::class;
    $stats = $store::stats();
    $why = $store::reasons();
    $steps = [
        ['Pilih produk', 'Tentukan layanan, durasi, dan garansi yang kamu mau dari daftar produk.', '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>'],
        ['Bayar', 'Scan QRIS dan bayar sesuai nominal, lalu klik “Saya Sudah Bayar”.', '<rect x="3" y="3" width="5" height="5" rx="1"/><rect x="16" y="3" width="5" height="5" rx="1"/><rect x="3" y="16" width="5" height="5" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1M21 12v.01M12 21v-1"/>'],
        ['Dapat akun', 'Detail login dan panduan aktivasi dikirim ke emailmu setelah pembayaran terverifikasi.', '<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4Z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>'],
    ];
    $badge = 'inline-flex items-center gap-1.5 rounded-full border border-[#F59E0B] bg-[#F59E0B]/15 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-[#0A0F1F] sm:text-xs';
    $star = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-[#F59E0B]" aria-hidden="true"><path d="m12 2 2.9 6.6L22 9.3l-5 4.9 1.2 7L12 17.8 5.8 21.2 7 14.2 2 9.3l7.1-.7L12 2Z" /></svg>';
@endphp

@push('meta')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $store::name(),
        'url' => url('/'),
        'logo' => $store::imageUrl('logo'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <main id="main">
        {{-- Hero --}}
        <section id="hero" class="hh scroll-mt-24">
            <div id="hh-fx" aria-hidden="true"></div>

            <div class="relative z-10 mx-auto grid max-w-6xl items-center gap-12 px-5 py-16 sm:px-8 md:py-24 lg:grid-cols-[1fr_1.15fr]">
                <div>
                    @if ($store::heroBadge() !== '')
                    <span class="hh-rise hh-chip inline-flex items-center gap-1.5 rounded-full bg-[#FEF3C7] px-3.5 py-1.5 text-xs font-semibold text-[#0D1428] ring-1 ring-inset ring-[#FCD34D]" style="--d:.05s">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-[#F59E0B]"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z" /></svg>
                        {{ $store::heroBadge() }}
                    </span>
                    @endif
                    <h1 class="hh-title mt-4 text-4xl font-extrabold tracking-tight text-[#0A0F1F] sm:text-5xl">
                        @foreach ($store::heroWords() as $i => $w)@if (! $w['glue'] && $i > 0) @endif<span class="hh-word{{ $w['gold'] ? ' hh-gold' : '' }}" style="--d:{{ number_format(0.15 + 0.1 * $i, 2) }}s">{{ $w['text'] }}</span>@endforeach

                    </h1>
                    <p class="hh-rise mt-5 max-w-xl text-base font-normal leading-relaxed text-[#374151] sm:text-lg" style="--d:.7s">
                        {{ $store::text('hero_subtitle') }}
                    </p>
                    <div class="hh-rise mt-8 flex flex-col gap-3 sm:flex-row" style="--d:.85s">
                        <a href="{{ route('products.index') }}" class="hh-shine hh-cta group inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-6 py-2.5 text-center text-sm font-semibold text-[#0A0F1F] active:scale-[0.99]">
                            Lihat Produk
                        </a>
                        <a href="{{ $wa }}" rel="noopener" class="hh-outline inline-flex items-center justify-center gap-2 rounded-2xl border border-[#D1D5DB] bg-white px-6 py-2.5 text-center text-sm font-semibold text-[#0D1428]">
                            <x-store.brand-icon name="whatsapp" class="text-base text-[#25D366]" />
                            Chat via WhatsApp
                        </a>
                    </div>
                    @php
                        $showOrders = $store::flag('stat_orders') && $stats['orders'] > 0;
                        $showRating = $store::flag('stat_rating') && $stats['reviews'] > 0;
                    @endphp
                    @if ($showOrders || $showRating)
                    <div class="mt-8 flex flex-wrap items-center gap-2">
                        @if ($showOrders)
                        <span class="hh-rise hh-chip inline-flex items-center gap-1.5 rounded-full bg-[#FEF3C7] py-1.5 pl-2 pr-3 text-sm text-[#0D1428] ring-1 ring-inset ring-[#FCD34D]" style="--d:1s">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[#F59E0B]"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                            <strong class="font-bold text-[#0A0F1F]">{{ number_format($stats['orders'], 0, ',', '.') }}</strong> pesanan selesai
                        </span>
                        @endif
                        @if ($showRating)
                        <span class="hh-rise hh-chip inline-flex items-center gap-1.5 rounded-full bg-[#FEF3C7] py-1.5 pl-2 pr-3 text-sm text-[#0D1428] ring-1 ring-inset ring-[#FCD34D]" style="--d:1.1s">
                            {!! $star !!}
                            Rating <strong class="font-bold text-[#0A0F1F]">{{ number_format($stats['rating'], 1, ',', '.') }}</strong>/5
                        </span>
                        @endif
                    </div>
                    @endif
                </div>
                <div class="hh-rise" style="--d:.3s">
                    <img src="{{ $store::imageUrl('hero_image') }}" alt="{{ $store::text('hero_alt') }}" {!! $store::imageSize('hero_image') !!} fetchpriority="high" class="h-auto w-full" />
                </div>
            </div>
        </section>

        {{-- Dipercaya kampus --}}
        @if ($store::flag('campus_enabled') && $store::campuses() !== [])
        <section class="bg-white">
            <div class="mx-auto max-w-6xl px-5 pb-10 sm:px-8 md:pb-14">
                <p class="text-center text-sm font-medium text-[#6B7280] sm:text-base">{{ $store::text('campus_title') }}</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-6 sm:gap-x-8 lg:flex-nowrap">
                    @foreach ($store::campuses() as $campus)
                        <img src="{{ $store::campusUrl($campus) }}" alt="{{ $campus['name'] }}" {!! $store::campusSize($campus) !!} loading="lazy" decoding="async" class="h-12 w-auto shrink-0 object-contain transition [filter:grayscale(.25)] hover:scale-105 hover:[filter:grayscale(0)] sm:h-14" />
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- Produk terlaris --}}
        @if ($bestSellers->isNotEmpty())
            <section id="terlaris" class="scroll-mt-24 bg-white">
                <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 md:py-14">
                    <div class="max-w-2xl">
                        <span class="{{ $badge }}">{!! $star !!} Pilihan Terbaik</span>
                        <h2 class="mt-4 text-[26px] font-bold tracking-tight text-[#0A0F1F] sm:text-4xl">Produk Terlaris</h2>
                        <span aria-hidden="true" class="mt-3 block h-1 w-14 rounded-full bg-[#F59E0B]"></span>
                        <p class="mt-4 max-w-[650px] text-sm leading-relaxed text-[#6B7280] sm:text-base">Langganan favorit yang paling banyak dipilih pelanggan ProAkses.</p>
                    </div>
                    <div class="mt-10 grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3 lg:gap-5 xl:gap-6">
                        @foreach ($bestSellers as $product)
                            <x-store.product-card :product="$product" layout="featured" />
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Semua produk --}}
        <section id="produk" class="scroll-mt-24 bg-white">
            <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 md:py-14">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-2xl">
                        <span class="{{ $badge }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-[#F59E0B]"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z" /></svg>
                            Produk
                        </span>
                        <h2 class="mt-4 text-[26px] font-bold tracking-tight text-[#0A0F1F] sm:text-4xl">Semua produk</h2>
                        <span aria-hidden="true" class="mt-3 block h-1 w-14 rounded-full bg-[#F59E0B]"></span>
                        <p class="mt-4 max-w-[650px] text-sm leading-relaxed text-[#6B7280] sm:text-base">Akun AI premium, langganan streaming, dan tools kreatif akun pribadi siap pakai.</p>
                    </div>
                    <a href="{{ route('products.index') }}" class="text-sm font-semibold text-[#0A0F1F] underline decoration-[#F59E0B] decoration-2 underline-offset-4 hover:text-[#F59E0B]">Lihat dengan filter</a>
                </div>
                @if ($products->isEmpty())
                    <p class="mt-10 rounded-2xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-500">Produk akan tampil di sini setelah ditambahkan.</p>
                @else
                    <div class="mt-10 grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($products as $product)
                            <x-store.product-card :product="$product" />
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Keunggulan --}}
        @if ($why['enabled'] && $why['items'] !== [])
        <section id="keunggulan" class="scroll-mt-24 bg-white" aria-labelledby="kw-title">
            <div class="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-20">
                <div class="mx-auto max-w-2xl text-center">
                    @if ($why['badge'] !== '')
                    <span class="{{ $badge }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-[#F59E0B]" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                        {{ $why['badge'] }}
                    </span>
                    @endif
                    <h2 id="kw-title" class="mt-4 text-[24px] font-bold tracking-tight text-[#0A0F1F] sm:text-4xl">{{ $why['title'] }}</h2>
                    <span aria-hidden="true" class="mx-auto mt-3 block h-1 w-14 rounded-full bg-[#F59E0B]"></span>
                    @if ($why['subtitle'] !== '')
                    <p class="mx-auto mt-4 max-w-[650px] text-[15px] leading-relaxed text-[#6B7280] sm:text-base">{{ $why['subtitle'] }}</p>
                    @endif
                </div>

                <ul class="mt-10 grid grid-cols-1 gap-3 sm:mt-12 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3 lg:gap-6">
                    @foreach ($why['items'] as $i => $item)
                        <li class="kw-card" style="--d:{{ $i * 0.08 }}s">
                            <span class="kw-num" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</span>
                            <span class="kw-ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $store::reasonIcon($item['icon']) !!}</svg></span>
                            <h3 class="relative mt-4 text-[17px] font-semibold text-[#0A0F1F]">{{ $item['title'] }}</h3>
                            <p class="relative mt-2 text-sm leading-relaxed text-[#6B7280]">{{ $item['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
        @endif

        {{-- Cara order --}}
        <section id="cara-order" class="cr-bg scroll-mt-24">
            <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 md:py-14">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="inline-flex items-center rounded-full bg-[#0A0F1F]/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#F59E0B] ring-1 ring-inset ring-[#0A0F1F]/10">Cara Order</span>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-[#0A0F1F] sm:text-4xl">Cara order</h2>
                    <span aria-hidden="true" class="mx-auto mt-4 block h-[3px] w-12 rounded-full bg-[#F59E0B]"></span>
                    <p class="mt-4 text-base text-[#6B7280]">Tiga langkah mudah.</p>
                </div>
                <ol class="mt-16 grid grid-cols-1 gap-y-14 md:grid-cols-3 md:gap-x-10 md:gap-y-0">
                    @foreach ($steps as $i => [$title, $text, $icon])
                        <li class="cr-card group relative flex flex-col items-center rounded-[20px] border border-[#E5E7EB] bg-gradient-to-br from-white to-[#F9FAFB] px-6 pb-7 pt-11 text-center shadow-[0_4px_20px_rgba(0,0,0,0.04)] hover:border-[#F59E0B]/50 hover:shadow-[0_8px_28px_rgba(0,0,0,0.08)]" style="--d:{{ $i * 0.1 }}s">
                            <span class="cr-num absolute -top-7 left-1/2 flex h-14 w-14 -translate-x-1/2 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-[#FBBF24] to-[#F59E0B] text-xl font-bold text-[#0A0F1F]">{{ $i + 1 }}</span>
                            <span class="relative flex h-16 w-16 items-center justify-center rounded-full bg-[#FEF3C7] text-[#F59E0B]">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                            </span>
                            <h3 class="relative mt-4 text-[17px] font-semibold text-[#0A0F1F]">{{ $title }}</h3>
                            <p class="relative mt-2 text-sm leading-relaxed text-[#6B7280]">{{ $text }}</p>
                            @unless ($loop->last)
                                <span aria-hidden="true" class="absolute left-1/2 top-full h-8 -translate-x-1/2 border-l-2 border-dashed border-[#F59E0B]/50 md:hidden"></span>
                                <span aria-hidden="true" class="absolute left-full top-1/2 hidden w-10 -translate-y-1/2 border-t-2 border-dashed border-[#F59E0B]/50 md:block"></span>
                            @endunless
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- FAQ (dikelola di admin) --}}
        @if ($faqs->isNotEmpty())
        <section id="faq" class="faq-bg scroll-mt-24">
            <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 md:py-14">
                <div class="grid gap-8 lg:grid-cols-[2fr_3fr] lg:gap-14">
                    <div class="lg:sticky lg:top-28 lg:self-start">
                        <span class="{{ $badge }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="text-[#F59E0B]" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg>
                            FAQ
                        </span>
                        <h2 class="mt-4 text-[28px] font-bold tracking-tight text-[#0A0F1F] sm:text-[32px]">Pertanyaan umum</h2>
                        <span aria-hidden="true" class="mt-3 block h-1 w-14 rounded-full bg-[#F59E0B]"></span>
                        <p class="mt-4 max-w-sm text-[15px] leading-relaxed text-[#6B7280] sm:text-base">Belum menemukan jawaban? Hubungi CS kami.</p>
                    </div>

                    <div>
                        <div class="overflow-hidden rounded-[20px] border border-[#E5E7EB] bg-white shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                            @foreach ($faqs as $i => $faq)
                                <details @if ($i === 0) open @endif class="faq-item faq-reveal group {{ $i === 0 ? 'is-open' : '' }}" style="--d:{{ min($i, 6) * 0.08 }}s">
                                    <summary class="flex min-h-[44px] cursor-pointer list-none items-center justify-between gap-4 px-5 py-5 text-[15px] font-semibold leading-snug text-[#0A0F1F] sm:px-7 sm:text-base [&::-webkit-details-marker]:hidden">
                                        {{ $faq->question }}
                                        <span class="faq-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span>
                                    </summary>
                                    <div class="faq-body"><div>
                                        <p class="max-w-[90%] px-5 pb-5 text-[14px] leading-relaxed text-[#6B7280] sm:px-7 sm:text-[15px]">{!! nl2br(e($faq->answer)) !!}</p>
                                    </div></div>
                                </details>
                            @endforeach
                        </div>
                        <p class="mt-4 text-sm"><a href="{{ route('faq') }}" class="font-semibold text-[#0A0F1F] underline decoration-[#F59E0B] decoration-2 underline-offset-4 hover:text-[#F59E0B]">Lihat semua pertanyaan</a></p>

                        <div class="relative mt-6 overflow-hidden rounded-[20px] bg-gradient-to-br from-[#0A0F1F] to-[#131A33] p-6 sm:p-8">
                            <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-[#F59E0B]/15 blur-2xl"></div>
                            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-lg font-semibold text-white">Masih ada pertanyaan?</p>
                                    <p class="mt-1 text-sm text-[#FEF3C7]/80">Tim CS kami siap membantu lewat WhatsApp.</p>
                                </div>
                                <a href="{{ $wa }}" rel="noopener" class="inline-flex min-h-[44px] shrink-0 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-5 py-2.5 text-sm font-semibold text-[#0A0F1F] transition hover:brightness-105 active:scale-[0.99]">
                                    <x-store.brand-icon name="whatsapp" class="text-base" />
                                    Chat via WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif
    </main>
@endsection
