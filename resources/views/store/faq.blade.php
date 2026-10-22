@extends('layouts.store')

@php
    $heading = $category ? 'FAQ '.$category->label() : 'Pusat Bantuan dan FAQ';
    $canonical = route('faq', $category ? ['kategori' => $category->value] : []);
    $pill = 'inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-semibold transition';
@endphp

@section('title', $heading.' — '.\App\Support\StoreProfile::name())
@section('description', 'Jawaban pertanyaan umum seputar pemesanan, pembayaran, akun, dan garansi di ProAkses.')
@section('canonical', $canonical)

@push('meta')
    @if ($q !== '')
        <meta name="robots" content="noindex, follow">
    @endif
    @if ($faqs->isNotEmpty() && $q === '')
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->answer],
            ])->values(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
@endpush

@section('content')
    <main id="main" class="mx-auto max-w-4xl px-5 py-8 sm:px-8 md:py-12">
        <nav aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1.5 text-[13px] text-[#6B7280] sm:text-sm">
                <li><a href="{{ route('home') }}" class="transition hover:text-[#F59E0B]">Beranda</a></li>
                <li aria-hidden="true" class="text-[#D1D5DB]">/</li>
                <li aria-current="page" class="font-medium text-[#0A0F1F]">FAQ</li>
            </ol>
        </nav>

        <header class="mt-6 max-w-2xl">
            <h1 class="text-[28px] font-bold tracking-tight text-[#0A0F1F] sm:text-4xl">{{ $heading }}</h1>
            <span aria-hidden="true" class="mt-3 block h-1 w-14 rounded-full bg-[#F59E0B]"></span>
            <p class="mt-4 text-sm leading-relaxed text-[#6B7280] sm:text-base">Temukan jawaban seputar pemesanan, pembayaran, akun, dan garansi.</p>
        </header>

        <div class="mt-8 flex flex-col gap-4">
            <form method="GET" action="{{ route('faq') }}" role="search" class="flex w-full gap-2 sm:max-w-md">
                @if ($category)
                    <input type="hidden" name="kategori" value="{{ $category->value }}">
                @endif
                <label for="q" class="sr-only">Cari pertanyaan</label>
                <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Cari pertanyaan" maxlength="60" autocomplete="off"
                    class="min-w-0 flex-1 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-2.5 text-sm text-[#0A0F1F] placeholder:text-[#9CA3AF] transition focus:border-[#F59E0B] focus:bg-white focus:outline-none">
                <button type="submit" class="rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-semibold text-[#0A0F1F] transition hover:border-[#F59E0B]">Cari</button>
            </form>

            <nav aria-label="Kategori FAQ" class="flex flex-wrap gap-2">
                <a href="{{ route('faq', array_filter(['q' => $q])) }}" @if (! $category) aria-current="page" @endif
                    class="{{ $pill }} {{ ! $category ? 'border-[#F59E0B] bg-[#F59E0B]/15 text-[#0A0F1F]' : 'border-[#E5E7EB] bg-white text-[#374151] hover:border-[#F59E0B]' }}">Semua <span class="text-xs text-[#4B5563]">{{ $total }}</span></a>
                @foreach ($categories as $c)
                    @if (($counts[$c->value] ?? 0) > 0)
                        <a href="{{ route('faq', array_filter(['kategori' => $c->value, 'q' => $q])) }}" @if ($category === $c) aria-current="page" @endif
                            class="{{ $pill }} {{ $category === $c ? 'border-[#F59E0B] bg-[#F59E0B]/15 text-[#0A0F1F]' : 'border-[#E5E7EB] bg-white text-[#374151] hover:border-[#F59E0B]' }}">{{ $c->label() }} <span class="text-xs text-[#4B5563]">{{ $counts[$c->value] }}</span></a>
                    @endif
                @endforeach
            </nav>
        </div>

        @if ($faqs->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-slate-200 p-10 text-center">
                <p class="text-base font-semibold text-[#0A0F1F]">{{ $total === 0 ? 'Belum ada FAQ' : 'Pertanyaan tidak ditemukan' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $total === 0 ? 'Pertanyaan umum akan tampil di sini.' : 'Coba kata kunci atau kategori lain, atau tanyakan langsung ke CS kami.' }}</p>
            </div>
        @else
            <section id="faq" class="mt-8">
                <div class="overflow-hidden rounded-[20px] border border-[#E5E7EB] bg-white shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                    @foreach ($faqs as $i => $faq)
                        <details @if ($i === 0) open @endif class="faq-item faq-reveal group {{ $i === 0 ? 'is-open' : '' }}" style="--d:{{ min($i, 6) * 0.05 }}s">
                            <summary class="flex min-h-[44px] cursor-pointer list-none items-center justify-between gap-4 px-5 py-5 text-[15px] font-semibold leading-snug text-[#0A0F1F] sm:px-7 sm:text-base [&::-webkit-details-marker]:hidden">
                                <span>
                                    {{ $faq->question }}
                                    @unless ($category)
                                        <span class="ml-2 align-middle text-[11px] font-medium text-[#6B7280]">{{ $faq->category->label() }}</span>
                                    @endunless
                                </span>
                                <span class="faq-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span>
                            </summary>
                            <div class="faq-body"><div>
                                <p class="max-w-[90%] px-5 pb-5 text-[14px] leading-relaxed text-[#6B7280] sm:px-7 sm:text-[15px]">{!! nl2br(e($faq->answer)) !!}</p>
                            </div></div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="relative mt-8 overflow-hidden rounded-[20px] bg-gradient-to-br from-[#0A0F1F] to-[#131A33] p-6 sm:p-8">
            <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-[#F59E0B]/15 blur-2xl"></div>
            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-lg font-semibold text-white">Belum menemukan jawabannya?</p>
                    <p class="mt-1 text-sm text-[#FEF3C7]/80">Tim CS kami siap membantu lewat WhatsApp.</p>
                </div>
                <a href="{{ \App\Support\Whatsapp::url() }}" rel="noopener" class="inline-flex min-h-[44px] shrink-0 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-5 py-2.5 text-sm font-semibold text-[#0A0F1F] transition hover:brightness-105 active:scale-[0.99]">
                    <x-store.brand-icon name="whatsapp" class="text-base" />
                    Chat via WhatsApp
                </a>
            </div>
        </div>
    </main>
@endsection
