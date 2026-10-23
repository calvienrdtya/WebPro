@extends('layouts.store')

@php
    // Label kategori AI sudah diawali "Akun" ("Akun AI"), kategori lain belum.
    $categoryName = $category ? (str_starts_with($category->name, 'Akun') ? $category->name : 'Akun '.$category->name) : null;
    $heading = $category ? $categoryName.' Premium' : 'Semua Produk';
    $canonical = route('products.index', $category ? ['kategori' => $category->slug] : []);
    $desc = $category
        ? "Daftar {$categoryName} premium di ProAkses: harga rupiah, pilihan durasi, dan garansi tersedia."
        : 'Daftar akun AI premium, streaming, dan tools kreatif di ProAkses: harga rupiah, pilihan durasi, dan garansi tersedia.';
    $pill = 'rounded-full border px-4 py-2 text-sm font-semibold transition';
@endphp

@section('title', $heading.' — '.\App\Support\StoreProfile::name())
@section('description', $desc)
@section('canonical', $canonical)

@push('meta')
    @if ($q !== '')
        <meta name="robots" content="noindex, follow">
    @endif
    @if ($products->previousPageUrl())
        <link rel="prev" href="{{ $products->previousPageUrl() }}">
    @endif
    @if ($products->nextPageUrl())
        <link rel="next" href="{{ $products->nextPageUrl() }}">
    @endif
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_values(array_filter([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Produk', 'item' => route('products.index')],
            $category ? ['@type' => 'ListItem', 'position' => 3, 'name' => $category->name, 'item' => $canonical] : null,
        ])),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <main id="main" class="mx-auto max-w-6xl px-5 py-8 sm:px-8 md:py-12">
        <nav aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1.5 text-[13px] text-[#6B7280] sm:text-sm">
                <li><a href="{{ route('home') }}" class="transition hover:text-[#F59E0B]">Beranda</a></li>
                <li aria-hidden="true" class="text-[#D1D5DB]">/</li>
                @if ($category)
                    <li><a href="{{ route('products.index') }}" class="transition hover:text-[#F59E0B]">Produk</a></li>
                    <li aria-hidden="true" class="text-[#D1D5DB]">/</li>
                    <li aria-current="page" class="font-medium text-[#0A0F1F]">{{ $category->name }}</li>
                @else
                    <li aria-current="page" class="font-medium text-[#0A0F1F]">Produk</li>
                @endif
            </ol>
        </nav>

        <header class="mt-6 max-w-2xl">
            <h1 class="text-[28px] font-bold tracking-tight text-[#0A0F1F] sm:text-4xl">{{ $heading }}</h1>
            <span aria-hidden="true" class="mt-3 block h-1 w-14 rounded-full bg-[#F59E0B]"></span>
            <p class="mt-4 text-sm leading-relaxed text-[#6B7280] sm:text-base">Akun AI premium, langganan streaming, dan tools kreatif akun pribadi siap pakai. Pilih durasi dan garansi di halaman produk.</p>
        </header>

        <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <nav aria-label="Kategori produk" class="flex flex-wrap gap-2">
                <a href="{{ route('products.index', $q !== '' ? ['q' => $q] : []) }}" @if (! $category) aria-current="page" @endif
                    class="{{ $pill }} {{ ! $category ? 'border-[#F59E0B] bg-[#F59E0B]/15 text-[#0A0F1F]' : 'border-[#E5E7EB] bg-white text-[#374151] hover:border-[#F59E0B]' }}">Semua</a>
                @foreach ($categories as $c)
                    <a href="{{ route('products.index', array_filter(['kategori' => $c->slug, 'q' => $q])) }}" @if ($category?->id === $c->id) aria-current="page" @endif
                        class="{{ $pill }} {{ $category?->id === $c->id ? 'border-[#F59E0B] bg-[#F59E0B]/15 text-[#0A0F1F]' : 'border-[#E5E7EB] bg-white text-[#374151] hover:border-[#F59E0B]' }}">{{ $c->name }}</a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('products.index') }}" role="search" class="flex w-full gap-2 sm:w-80">
                @if ($category)
                    <input type="hidden" name="kategori" value="{{ $category->slug }}">
                @endif
                <label for="q" class="sr-only">Cari produk</label>
                <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Cari produk" maxlength="60" autocomplete="off"
                    class="min-w-0 flex-1 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-2.5 text-sm text-[#0A0F1F] placeholder:text-[#9CA3AF] transition focus:border-[#F59E0B] focus:bg-white focus:outline-none">
                <button type="submit" class="rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-semibold text-[#0A0F1F] transition hover:border-[#F59E0B]">Cari</button>
            </form>
        </div>

        @if ($products->isEmpty())
            <div class="mt-10 rounded-2xl border border-dashed border-slate-200 p-10 text-center">
                <p class="text-base font-semibold text-[#0A0F1F]">Produk tidak ditemukan</p>
                <p class="mt-1 text-sm text-slate-500">Coba kata kunci atau kategori lain.</p>
                <a href="{{ route('products.index') }}" class="mt-5 inline-block rounded-2xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-6 py-2.5 text-sm font-semibold text-[#0A0F1F]">Lihat semua produk</a>
            </div>
        @else
            {{-- Judul bagian untuk pembaca layar dan urutan judul (h1 -> h2 -> h3 kartu). --}}
            <h2 class="sr-only">Daftar produk</h2>
            <div class="mt-8 grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav aria-label="Halaman" class="mt-10 flex items-center justify-between gap-3 text-sm">
                    @if ($products->previousPageUrl())
                        <a href="{{ $products->previousPageUrl() }}" rel="prev" class="rounded-xl border border-[#E5E7EB] bg-white px-4 py-2 font-semibold text-[#0A0F1F] transition hover:border-[#F59E0B]">Sebelumnya</a>
                    @else
                        <span></span>
                    @endif
                    <span class="text-[#6B7280]">Halaman {{ $products->currentPage() }} dari {{ $products->lastPage() }}</span>
                    @if ($products->nextPageUrl())
                        <a href="{{ $products->nextPageUrl() }}" rel="next" class="rounded-xl border border-[#E5E7EB] bg-white px-4 py-2 font-semibold text-[#0A0F1F] transition hover:border-[#F59E0B]">Berikutnya</a>
                    @else
                        <span></span>
                    @endif
                </nav>
            @endif
        @endif
    </main>
@endsection
