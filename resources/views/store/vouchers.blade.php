@extends('layouts.store')

@section('title', 'Voucher Diskon Akun Premium — '.\App\Support\StoreProfile::name())
@section('description', 'Klaim voucher ProAkses dan hemat lebih banyak untuk setiap pembelian akun premium.')
@section('canonical', route('vouchers.index'))

@if ($filter !== 'all')
    @push('meta')
        <meta name="robots" content="noindex, follow">
    @endpush
@endif

@php
    $tabs = ['all' => ['Semua', route('vouchers.index')], 'tersedia' => ['Tersedia', route('vouchers.index', ['status' => 'tersedia'])]];
    if (auth()->check()) {
        $tabs['diklaim'] = ['Diklaim', route('vouchers.index', ['status' => 'diklaim'])];
    }
    $icoCal = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>';
    $icoTag = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 12 22l-9-9V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>';
@endphp


@section('content')
    <main id="main" style="background: var(--vo-page)">
        {{-- Hero + tab --}}
        <section class="vh" aria-labelledby="vh-title">
            <div class="vh-dots" aria-hidden="true"></div>
            <div class="vh-spot" aria-hidden="true"></div>

            <div class="relative mx-auto max-w-6xl px-5 pb-10 pt-12 text-center sm:px-8 sm:pb-12 sm:pt-16">
                <span class="vo-reveal inline-flex items-center gap-1.5 rounded-full border border-[#F59E0B] bg-[#F59E0B]/15 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-[#FBBF24] sm:text-xs" style="--d:.05s">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
                    Promo &amp; Reward
                </span>
                <h1 id="vh-title" class="vo-reveal mt-4 text-[30px] font-bold leading-[1.15] tracking-tight text-white sm:text-[46px]" style="--d:.12s">Voucher Spesial</h1>
                <span class="vo-reveal mx-auto mt-3.5 block h-1 w-14 rounded-full bg-[#F59E0B]" style="--d:.18s" aria-hidden="true"></span>
                <p class="vo-reveal mx-auto mt-4 max-w-[640px] text-[15px] leading-relaxed text-[#FEF3C7]/80 sm:text-base" style="--d:.24s">Klaim voucher dan hemat lebih banyak untuk setiap pembelian akun premium kamu.</p>

                <dl class="vh-stats vo-reveal" style="--d:.32s">
                    <div class="vh-stat"><dt>Voucher Tersedia</dt><dd>{{ $availableCount }}</dd></div>
                    <div class="vh-stat"><dt>Voucher Saya</dt><dd>{{ auth()->check() ? $claimedCount : '-' }}</dd></div>
                </dl>

                <div class="vo-reveal mt-9 flex justify-center" style="--d:.4s">
                    <nav class="vt" aria-label="Filter voucher">
                        @foreach ($tabs as $key => [$label, $href])
                            <a href="{{ $href }}" class="vt-btn" @if ($filter === $key) aria-current="page" @endif>{{ $label }} <span class="vt-n">{{ $counts[$key] }}</span></a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </section>

        {{-- Daftar voucher --}}
        <section class="mx-auto max-w-6xl px-5 pb-6 pt-10 sm:px-8 sm:pt-12" aria-label="Daftar voucher">
            @if ($vouchers->isEmpty())
                <div class="ve">
                    <svg class="text-[#F59E0B] opacity-40" width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
                    <p class="mt-4 text-lg font-semibold text-[#0A0F1F]">{{ $filter === 'diklaim' ? 'Belum ada voucher yang kamu klaim' : 'Belum ada voucher tersedia' }}</p>
                    <p class="mt-1 max-w-sm text-sm text-[#6B7280]">{{ $filter === 'diklaim' ? 'Voucher yang kamu klaim akan muncul di sini.' : 'Voucher akan muncul di sini setelah ada promo baru dari ProAkses.' }}</p>
                    <a href="{{ $filter === 'all' ? route('products.index') : route('vouchers.index') }}" class="ve-btn">{{ $filter === 'all' ? 'Lihat Produk' : 'Lihat semua voucher' }}</a>
                </div>
            @else
                <div class="vg">
                    @foreach ($vouchers as $i => $v)
                        @include('store.partials.voucher-card', ['v' => $v, 'i' => $i])
                    @endforeach
                </div>
            @endif
        </section>

        {{-- CTA --}}
        <section class="mx-auto max-w-6xl px-5 pb-14 pt-8 sm:px-8 sm:pb-20" aria-labelledby="vcta-title">
            <div class="vcta">
                <div class="relative">
                    <h2 id="vcta-title" class="text-[21px] font-bold text-white sm:text-2xl">Belum punya voucher?</h2>
                    <p class="mt-1.5 max-w-[520px] text-[15px] leading-relaxed text-[#FEF3C7]/80">Belanja sekarang dan dapatkan voucher spesial untuk pembelian berikutnya.</p>
                </div>
                <a href="{{ route('products.index') }}" class="vcta-btn">Mulai Belanja <span aria-hidden="true">&rarr;</span></a>
            </div>
        </section>
    </main>
@endsection
