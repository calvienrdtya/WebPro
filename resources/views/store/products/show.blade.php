@extends('layouts.store')

@php
    $stock = (int) $product->stock_count;
    $sold = (int) $product->sold_count;
    $first = $options->first();
    $hasVariants = $first['id'] !== null;
    $accountDays = config('proakses.account_days');
    $title = $product->meta_title ?: $product->name.' — '.\App\Support\StoreProfile::name();
    $description = $product->meta_description ?: \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', (string) $product->description)) ?: "Beli {$product->name} dengan harga rupiah di ProAkses.", 155);
    // Pratinjau tautan dan data terstruktur: varian JPG gambar produk (WebP belum terbaca semua aplikasi).
    $image = $product->image ? url(app(\App\Support\ImageOptimizer::class)->shareUrl($product->image)) : \App\Support\StoreProfile::imageUrl('og_image');
    $minPrice = $options->min('final_price');
    $maxPrice = $options->max('final_price');
    $availability = $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

    $buyData = [
        'slug' => $product->slug,
        'fee' => $serviceFee,
        'inStock' => $stock > 0,
        'auth' => auth()->check(),
        'prefill' => ['name' => (string) (old('name') ?? $prefill['name'] ?? ''), 'email' => (string) (old('email') ?? $prefill['email'] ?? ''), 'wa' => (string) (old('wa') ?? $prefill['phone'] ?? '')],
        'options' => $options->map(fn ($o) => ['title' => $o['title'], 'months' => $o['months'], 'list' => $o['list_price'], 'final' => $o['final_price'], 'discount' => $o['discount']])->values(),
        'warranties' => $warranties->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'days' => $w->duration_days, 'price' => $w->price])->values(),
    ];
@endphp

@section('title', $title)
@section('description', $description)
@section('canonical', route('products.show', $product->slug))
@section('og_type', 'product')
@section('og_image', $image)

@push('meta')
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $description,
        'image' => [$image],
        'sku' => $product->slug,
        'category' => $product->category->label(),
        'aggregateRating' => $reviewSummary['count'] > 0 ? [
            '@type' => 'AggregateRating',
            'ratingValue' => $reviewSummary['average'],
            'reviewCount' => $reviewSummary['count'],
            'bestRating' => 5,
            'worstRating' => 1,
        ] : null,
        'review' => $reviewSummary['count'] > 0 ? $reviews->take(5)->map(fn ($r) => [
            '@type' => 'Review',
            'author' => ['@type' => 'Person', 'name' => $r->displayName()],
            'datePublished' => $r->created_at->toDateString(),
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $r->rating, 'bestRating' => 5, 'worstRating' => 1],
            'reviewBody' => $r->body,
        ])->values()->all() : null,
        'offers' => $options->count() > 1 ? [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'IDR',
            'lowPrice' => $minPrice,
            'highPrice' => $maxPrice,
            'offerCount' => $options->count(),
            'availability' => $availability,
            'url' => route('products.show', $product->slug),
        ] : [
            '@type' => 'Offer',
            'priceCurrency' => 'IDR',
            'price' => $first['final_price'],
            'availability' => $availability,
            'url' => route('products.show', $product->slug),
        ],
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Produk', 'item' => route('products.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $product->category->label(), 'item' => route('products.index', ['kategori' => $product->category->value])],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $product->name, 'item' => route('products.show', $product->slug)],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <main id="main">
        <nav aria-label="Breadcrumb" class="mx-auto max-w-6xl px-5 pt-6 sm:px-8">
            <ol class="flex flex-wrap items-center gap-1.5 text-[13px] text-[#6B7280] sm:text-sm">
                <li><a href="{{ route('home') }}" class="transition hover:text-[#B45309]">Beranda</a></li>
                <li aria-hidden="true" class="text-[#D1D5DB]">/</li>
                <li><a href="{{ route('products.index') }}" class="transition hover:text-[#B45309]">Produk</a></li>
                <li aria-hidden="true" class="text-[#D1D5DB]">/</li>
                <li><a href="{{ route('products.index', ['kategori' => $product->category->value]) }}" class="transition hover:text-[#B45309]">{{ $product->category->label() }}</a></li>
                <li aria-hidden="true" class="text-[#D1D5DB]">/</li>
                <li aria-current="page" class="font-medium text-[#0A0F1F]">{{ $product->name }}</li>
            </ol>
        </nav>

        <section class="mx-auto max-w-6xl px-5 py-8 sm:px-8 md:py-12">
            <div class="grid gap-10 lg:grid-cols-2">
                {{-- Kolom kiri: galeri dan penjelasan --}}
                <div>
                    <div class="relative overflow-hidden rounded-[20px] border border-[#E5E7EB] bg-slate-100 shadow-[0_4px_20px_rgba(0,0,0,0.06)]">
                        @if ($product->image_url)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" width="800" height="640" fetchpriority="high" class="aspect-[5/4] h-full w-full object-cover" />
                        @else
                            <div class="aspect-[5/4]"></div>
                        @endif
                        @if ($first['discount'] > 0)
                            <span class="absolute right-3 top-3 rounded-full bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-wide text-[#0A0F1F] shadow-md shadow-[#F59E0B]/30 sm:right-4 sm:top-4 sm:text-xs">Diskon {{ $first['discount'] }}%</span>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        @php
                            $cards = array_values(array_filter([
                                ['Dikirim ke email', 'Setelah pembayaran terverifikasi', '<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z"/>'],
                                ['Aktif '.$accountDays.' hari', 'Masa aktif per akun', '<path d="M7 2v3M17 2v3M3.5 9h17M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 19 20.5H5A1.5 1.5 0 0 1 3.5 19V6A1.5 1.5 0 0 1 5 4.5Z"/>'],
                                $first['discount'] > 0 ? ['Diskon '.$first['discount'].'%', 'Dari harga normal', '<path d="M20.6 13.4 12 22l-9-9V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>'] : null,
                                $warranties->isNotEmpty() ? [$warranties->count().' pilihan garansi', 'Ganti akun bila bermasalah', '<path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/>'] : null,
                                [$stock > 0 ? $stock.' akun' : 'Habis', 'Stok tersedia', '<path d="M21 8 12 3 3 8m18 0-9 5m9-5v8l-9 5m0-8L3 8m9 5v8M3 8v8l9 5"/>'],
                                ['Bayar QRIS', 'Semua e-wallet', '<path d="M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/><path d="M3 10h18"/>'],
                            ]));
                        @endphp
                        @foreach ($cards as [$head, $sub, $icon])
                            <div class="flex flex-col items-center rounded-2xl border border-[#E5E7EB] bg-white p-4 text-center shadow-[0_2px_8px_rgba(0,0,0,0.04)] transition hover:border-[#F59E0B]/50">
                                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B]">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                                </span>
                                <p class="mt-3 text-sm font-bold text-[#0A0F1F]">{{ $head }}</p>
                                <p class="mt-1 text-xs text-[#6B7280]">{{ $sub }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if (! empty($product->highlights))
                        <div class="mt-8">
                            <h2 class="text-lg font-bold text-[#0A0F1F]">Yang kamu dapat</h2>
                            <ul class="mt-4 space-y-2.5 text-sm text-[#374151]">
                                @foreach ($product->highlights as $point)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-[#F59E0B]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 10.7a1 1 0 1 1 1.4-1.4l3.1 3.1 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                        <span>{{ $point }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($product->specs))
                        <div class="mt-8">
                            <h2 class="text-lg font-bold text-[#0A0F1F]">Spesifikasi</h2>
                            <dl class="mt-4 divide-y divide-[#F3F4F6] overflow-hidden rounded-2xl border border-[#E5E7EB] text-sm">
                                @foreach ($product->specs as $spec)
                                    <div class="grid grid-cols-[9rem_1fr] gap-3 px-4 py-3 sm:grid-cols-[12rem_1fr]">
                                        <dt class="text-[#6B7280]">{{ $spec['label'] ?? '' }}</dt>
                                        <dd class="font-medium text-[#0A0F1F]">{{ $spec['value'] ?? '' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    {{-- Ulasan pembeli (hanya ulasan asli yang sudah disetujui admin) --}}
                    <section id="ulasan" class="mt-8 scroll-mt-24" x-data="{ open: false, info: false }" x-effect="document.body.classList.toggle('overflow-hidden', open)" @keydown.escape.window="open = false">
                        <div class="overflow-hidden rounded-3xl bg-white ring-1 ring-slate-100">
                            <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-4 sm:px-6">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B] ring-1 ring-inset ring-[#F59E0B]/30">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                </span>
                                <div>
                                    <h2 class="text-base font-semibold text-[#0A0F1F]">Ulasan pembeli</h2>
                                    <p class="text-sm text-[#6B7280]">Penilaian dari pembeli produk ini.</p>
                                </div>
                            </div>

                            @if ($reviewSummary['count'] > 0)
                                <div class="flex items-center gap-4 px-4 py-5 sm:px-6">
                                    <span class="text-5xl font-semibold tracking-tight text-[#0A0F1F]">{{ number_format($reviewSummary['average'], 1, ',', '') }}</span>
                                    <span class="h-12 w-px bg-slate-200" aria-hidden="true"></span>
                                    <div>
                                        <x-store.stars :rating="$reviewSummary['average']" class="h-5 w-5" />
                                        <p class="mt-1.5 text-sm text-[#374151]">{{ number_format($reviewSummary['average'], 1, ',', '') }} &middot; <span class="font-medium text-[#0A0F1F]">{{ number_format($reviewSummary['count'], 0, ',', '.') }} ulasan</span></p>
                                    </div>
                                </div>
                                <div class="space-y-3 border-t border-slate-100 px-4 py-5 sm:px-6">
                                    @foreach ($reviewSummary['distribution'] as $star => $total)
                                        <div class="flex items-center gap-3">
                                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-[#F3F4F6]"><div class="h-full rounded-full bg-[#F59E0B]" style="width: {{ round($total / $reviewSummary['count'] * 100) }}%"></div></div>
                                            <span class="flex w-14 items-center justify-end gap-1 text-sm font-medium tabular-nums text-[#6B7280]">{{ $star }}.0
                                                <svg class="h-3.5 w-3.5 text-[#F59E0B]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                                <span class="sr-only">{{ $total }} ulasan</span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="px-4 py-8 text-center sm:px-6">
                                    <p class="text-base font-semibold text-[#0A0F1F]">Belum ada ulasan</p>
                                    <p class="mt-1 text-sm text-[#6B7280]">Jadilah pembeli pertama yang menilai produk ini.</p>
                                </div>
                            @endif

                            <div class="border-t border-slate-100 px-4 py-4 sm:px-6">
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <button type="button" @click="info = !info" :aria-expanded="info"
                                        class="flex flex-1 items-center justify-center gap-2 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-sm font-semibold text-[#374151] transition hover:bg-[#F9FAFB]">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                                        Tulis ulasan
                                    </button>
                                    @if ($reviews->isNotEmpty())
                                        <button type="button" @click="open = true" aria-haspopup="dialog"
                                            class="flex flex-1 items-center justify-center gap-2 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-sm font-semibold text-[#374151] transition hover:bg-[#F9FAFB]">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                            Lihat ulasan lainnya
                                        </button>
                                    @endif
                                </div>
                                <p x-show="info" x-cloak role="status" class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-inset ring-amber-200">
                                    Ulasan hanya bisa ditulis oleh pembeli produk ini. Setelah pesananmu lunas, tulis ulasan dari <a href="{{ route('profil', ['tab' => 'pesanan']) }}" class="font-semibold underline">Pesanan Saya</a>.
                                </p>
                            </div>
                        </div>

                        @if ($reviews->isNotEmpty())
                            {{-- Pop up daftar ulasan --}}
                            <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="ulasan-modal-title"
                                x-effect="if (open) $nextTick(() => $refs.close && $refs.close.focus())">
                                <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-[#0A0F1F]/60 backdrop-blur-sm" @click="open = false" aria-hidden="true"></div>
                                <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-0 sm:scale-95" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                    class="relative flex max-h-[88vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl">
                                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                                        <div>
                                            <h2 id="ulasan-modal-title" class="text-base font-semibold text-[#0A0F1F]">Ulasan pembeli</h2>
                                            <div class="mt-1.5 flex items-center gap-2">
                                                <x-store.stars :rating="$reviewSummary['average']" class="h-4 w-4" />
                                                <p class="text-sm text-[#374151]">{{ number_format($reviewSummary['average'], 1, ',', '') }} &middot; {{ number_format($reviewSummary['count'], 0, ',', '.') }} ulasan</p>
                                            </div>
                                        </div>
                                        <button type="button" x-ref="close" @click="open = false" aria-label="Tutup ulasan"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#6B7280] transition hover:bg-slate-100 hover:text-[#0A0F1F]">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <ul class="space-y-3 overflow-y-auto bg-[#F9FAFB] px-4 py-4 sm:px-6">
                                        @foreach ($reviews as $review)
                                            <li class="rounded-2xl bg-white p-4 ring-1 ring-slate-100">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="flex items-center gap-2.5">
                                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-sm font-bold text-[#B45309] ring-1 ring-inset ring-[#F59E0B]/30">{{ $review->initial() }}</span>
                                                        <div>
                                                            <p class="text-sm font-semibold text-[#0A0F1F]">{{ $review->displayName() }}</p>
                                                            <p class="text-xs text-[#9CA3AF]">{{ $review->created_at->diffForHumans() }}</p>
                                                        </div>
                                                    </div>
                                                    <x-store.stars :rating="$review->rating" />
                                                </div>
                                                <p class="mt-3 text-sm leading-relaxed text-[#374151]">{!! nl2br(e($review->body)) !!}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </section>
                </div>

                {{-- Kolom kanan: panel beli --}}
                <div x-data="productBuy(@js($buyData))">
                    <div class="flex flex-wrap items-center gap-1.5">
                        @if ($stock === 0)
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[11.5px] font-semibold text-slate-600">Stok habis</span>
                        @elseif ($stock <= 5)
                            <span class="inline-flex items-center rounded-full border border-[#DC2626]/30 bg-[#DC2626]/10 px-3 py-1 text-[11.5px] font-semibold text-[#B91C1C] motion-safe:animate-blink-slow">Sisa stok {{ $stock }}</span>
                        @else
                            <span class="inline-flex items-center rounded-full border border-[#10B981]/30 bg-[#10B981]/10 px-3 py-1 text-[11.5px] font-semibold text-[#047857]">Stok tersedia</span>
                        @endif
                        @if ($sold > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#F59E0B]/30 bg-[#F59E0B]/10 px-3 py-1 text-[11.5px] font-semibold text-[#B45309]">{{ number_format($sold, 0, ',', '.') }} akun terjual</span>
                        @endif
                    </div>

                    <h1 class="mt-4 text-[28px] font-bold tracking-tight text-[#0A0F1F] sm:text-[32px]">{{ $product->name }}</h1>
                    <span aria-hidden="true" class="mt-2 block h-1 w-14 rounded-full bg-[#F59E0B]"></span>

                    @if ($reviewSummary['count'] > 0)
                        <a href="#ulasan" class="mt-4 inline-flex items-center gap-2">
                            <x-store.stars :rating="$reviewSummary['average']" class="h-[17px] w-[17px]" />
                            <span class="text-base font-bold text-[#0A0F1F]">{{ number_format($reviewSummary['average'], 1, ',', '') }}</span>
                            <span class="text-[13px] text-[#6B7280] underline decoration-dotted underline-offset-4">{{ number_format($reviewSummary['count'], 0, ',', '.') }} ulasan</span>
                        </a>
                    @endif

                    @if ($product->description)
                        <p class="mt-4 text-[15px] leading-[1.7] text-[#374151]">{!! nl2br(e($product->description)) !!}</p>
                    @endif

                    <div class="relative mt-6 overflow-hidden rounded-[20px] border border-[#F59E0B]/40 border-l-4 border-l-[#F59E0B] bg-white p-5 shadow-[0_4px_20px_rgba(0,0,0,0.04)] sm:p-6">
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span class="text-[30px] font-extrabold leading-none tracking-tight text-[#0A0F1F] sm:text-[32px]" x-text="rp(offer.final)">@rupiah($first['final_price'])</span>
                            <span class="text-sm font-medium text-[#6B7280] line-through sm:text-[15px]" x-show="offer.discount > 0" x-text="rp(offer.list)" @if ($first['discount'] === 0) style="display:none" @endif>@rupiah($first['list_price'])</span>
                            <span class="rounded-full bg-[#047857] px-2.5 py-1 text-[11.5px] font-bold text-white" x-show="offer.discount > 0" x-text="'Hemat ' + offer.discount + '%'" @if ($first['discount'] === 0) style="display:none" @endif>Hemat {{ $first['discount'] }}%</span>
                        </div>
                        <p class="mt-1.5 text-[12.5px] text-[#6B7280]" x-text="'Untuk ' + offer.months + ' bulan' + (offer.months > 1 ? ' (' + offer.months + ' akun, dikirim satu per bulan)' : '')">Untuk {{ $first['months'] }} bulan</p>

                        @if ($hasVariants)
                            <fieldset class="mt-5">
                                <legend class="text-[13.5px] font-semibold text-[#0A0F1F]">Pilih durasi</legend>
                                <div class="mt-2.5 grid grid-cols-3 gap-2">
                                    @foreach ($options as $i => $o)
                                        <label class="block cursor-pointer">
                                            <input type="radio" name="durasi" value="{{ $i }}" x-model.number="optIndex" @checked($i === 0) class="peer sr-only">
                                            {{-- Desain kartu durasi mengikuti frontend/produk.html: terpilih = gradasi emas. --}}
                                            <span class="btn-shine block rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-3 py-2.5 text-center text-[13.5px] font-semibold text-[#0A0F1F] transition duration-200 hover:border-[#F59E0B] hover:bg-white peer-checked:border-[#F59E0B] peer-checked:bg-gradient-to-r peer-checked:from-[#FBBF24] peer-checked:to-[#F59E0B] peer-checked:font-bold peer-checked:shadow-[0_4px_12px_rgba(245,158,11,0.3)] peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#F59E0B]">{{ $o['title'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        @if ($warranties->isNotEmpty())
                        <fieldset class="mt-5">
                            <legend class="text-[13.5px] font-semibold text-[#0A0F1F]">Pilih garansi</legend>
                            <div class="mt-2.5 space-y-2">
                                @foreach ($warranties as $w)
                                    <label class="block cursor-pointer">
                                        <input type="radio" name="garansi" value="{{ $w->id }}" x-model="warrantyId" @checked($loop->first) class="peer sr-only">
                                        <span class="flex items-center justify-between gap-3 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 transition peer-checked:border-[#F59E0B] peer-checked:bg-[#FFFBEB] peer-checked:shadow-[0_0_0_.5px_#F59E0B] peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#F59E0B]">
                                            <span>
                                                <span class="block text-sm font-semibold text-[#0A0F1F]">{{ $w->name }}</span>
                                                <span class="block text-xs text-[#6B7280]">Berlaku {{ $w->duration_days }} hari per akun</span>
                                            </span>
                                            <span class="text-sm font-semibold tabular-nums text-[#0A0F1F]">{{ $w->price === 0 ? 'Gratis' : 'Rp '.number_format($w->price, 0, ',', '.') }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        @endif
                    </div>

                    <form id="buy-form" method="POST" action="{{ route('checkout.store', $product->slug) }}" @submit.prevent="submit($el)" novalidate class="mt-5 space-y-3">
                        @csrf
                        <input type="hidden" name="option" :value="optIndex">
                        <input type="hidden" name="garansi" :value="warrantyId">
                        <input type="hidden" name="voucher" :value="voucherCode">
                        {{-- Form pemesan (desain mengikuti frontend/produk.html) --}}
                        <div class="rounded-[20px] border border-[#E5E7EB] bg-white p-5 shadow-[0_4px_20px_rgba(0,0,0,0.04)] transition hover:border-[#F59E0B]/40 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                                </span>
                                <h2 class="text-base font-semibold text-[#0A0F1F]">Isi form order</h2>
                            </div>
                            <div class="mt-4 flex flex-col gap-3">
                                <div>
                                    <label for="f-name" class="block text-[13.5px] font-medium text-[#374151]">Nama lengkap</label>
                                    <input id="f-name" name="name" type="text" required autocomplete="name" placeholder="Nama sesuai identitas" x-model="name" @input="error = ''"
                                        class="mt-1.5 w-full rounded-xl border bg-[#F9FAFB] px-4 py-2.5 text-[15px] text-[#0A0F1F] placeholder:text-[#9CA3AF] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.1)] focus:outline-none" :class="error && !name.trim() ? 'border-rose-400' : 'border-[#E5E7EB]'" />
                                </div>
                                <div>
                                    <label for="f-email" class="block text-[13.5px] font-medium text-[#374151]">Email</label>
                                    <input id="f-email" name="email" type="email" required autocomplete="email" placeholder="email@contoh.com" x-model="email" @input="error = ''"
                                        class="mt-1.5 w-full rounded-xl border bg-[#F9FAFB] px-4 py-2.5 text-[15px] text-[#0A0F1F] placeholder:text-[#9CA3AF] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.1)] focus:outline-none" :class="error && !email.trim() ? 'border-rose-400' : 'border-[#E5E7EB]'" />
                                </div>
                                <div>
                                    <label for="f-wa" class="block text-[13.5px] font-medium text-[#374151]">Nomor WhatsApp</label>
                                    <input id="f-wa" name="wa" type="tel" required inputmode="numeric" autocomplete="tel" placeholder="08xxxxxxxxxx" x-model="wa" @input="error = ''"
                                        class="mt-1.5 w-full rounded-xl border bg-[#F9FAFB] px-4 py-2.5 text-[15px] text-[#0A0F1F] placeholder:text-[#9CA3AF] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.1)] focus:outline-none" :class="error && !wa.trim() ? 'border-rose-400' : 'border-[#E5E7EB]'" />
                                </div>
                                <div>
                                    <label for="f-voucher" class="block text-[13.5px] font-medium text-[#374151]">Kode voucher (opsional)</label>
                                    <div class="flex gap-2">
                                        <input id="f-voucher" type="text" autocomplete="off" placeholder="Contoh: HEMAT50" maxlength="30" x-model="voucherInput"
                                            @keydown.enter.prevent="applyCode(voucherInput)" @input="onVoucherInput()"
                                            class="mt-1.5 w-full rounded-xl border bg-[#F9FAFB] px-4 py-2.5 text-[15px] text-[#0A0F1F] placeholder:text-[#9CA3AF] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.1)] focus:outline-none uppercase placeholder:normal-case" :class="'border-[#E5E7EB]'" />
                                        <button id="f-voucher-apply" type="button" @click="applyCode(voucherInput)" :disabled="checking"
                                            class="mt-1.5 shrink-0 rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-semibold text-[#0A0F1F] transition hover:border-[#F59E0B] disabled:opacity-60">Pakai</button>
                                    </div>
                                    <p id="f-voucher-msg" class="mt-1.5 text-xs font-medium" :class="voucherOk ? 'text-[#047857]' : 'text-[#DC2626]'" x-show="voucherMsg" x-text="voucherMsg" aria-live="polite" x-cloak></p>

                                    <div class="vp">
                                        <div class="vp-head">
                                            <div>
                                                <p class="vp-title">Voucher yang Bisa Dipakai</p>
                                                <p class="vp-sub">Pilih voucher di bawah untuk otomatis terpasang.</p>
                                            </div>
                                            <a href="{{ route('vouchers.index') }}" class="vp-link">Lihat semua &rarr;</a>
                                        </div>
                                        @if (count($voucherChoices))
                                            <div class="vp-list vp-list--scroll" aria-label="Daftar voucher">
                                                @foreach ($voucherChoices as $i => $vc)
                                                    <button type="button" class="vp-card" :aria-pressed="voucherCode === @js($vc['code'])" style="--d:{{ $i * 0.05 }}s"
                                                        @click="voucherCode === @js($vc['code']) ? clearVoucher() : (voucherInput = @js($vc['code']), applyCode(@js($vc['code'])))"
                                                        aria-label="Pakai voucher {{ $vc['title'] }}, kode {{ $vc['code'] }}">
                                                        <span class="vp-left" aria-hidden="true"><span class="vp-val {{ strlen($vc['value']) > 5 ? 'vp-val--sm' : '' }}">{{ $vc['value'] }}</span><span class="vp-lab">Diskon</span></span>
                                                        <span class="vp-right">
                                                            <span class="vp-name">{{ $vc['title'] }}</span>
                                                            <span class="vp-date"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>{{ $vc['until'] }}</span>
                                                            <span class="vp-row"><span class="vp-code">{{ $vc['code'] }}</span></span>
                                                        </span>
                                                        <span class="vp-check" role="img" aria-label="Voucher terpasang"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg></span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="vp-empty">
                                                <p>Belum ada voucher tersedia.</p>
                                                <p>{{ auth()->check() ? 'Klaim voucher di halaman Voucher agar muncul di sini.' : 'Masuk dan klaim voucher agar muncul di sini.' }}</p>
                                                <a href="{{ route('vouchers.index') }}" class="vp-link mt-2 inline-block">Lihat halaman Voucher &rarr;</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <p id="f-error" x-show="error" x-text="error" x-cloak role="alert" class="text-xs font-medium text-rose-700"></p>
                            </div>
                        </div>

                        {{-- Rincian harga --}}
                        <div class="rounded-[20px] border border-[#E5E7EB] bg-white p-5 shadow-[0_4px_20px_rgba(0,0,0,0.04)] transition hover:border-[#F59E0B]/40 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h9l3 3v17l-2.5-1.5L13 22l-2.5-1.5L8 22l-2.5-1.5L4 22V4a2 2 0 0 1 2-2Z"/><path d="M8 7h6M8 11h6M8 15h4"/></svg>
                                </span>
                                <h2 class="text-base font-semibold text-[#0A0F1F]">Rincian harga</h2>
                            </div>
                            <dl class="mt-5 space-y-3.5 text-sm tabular-nums sm:space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-[#6B7280]">Harga produk</dt>
                                    <dd class="font-semibold text-[#0A0F1F]" x-text="rp(offer.list)">@rupiah($first['list_price'])</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-[#6B7280]">Garansi</dt>
                                    <dd class="font-semibold text-[#0A0F1F]" x-text="warranty ? (warranty.price === 0 ? 'Gratis' : rp(warranty.price)) : '-'">-</dd>
                                </div>
                                @if ($serviceFee > 0)
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-[#6B7280]">Biaya layanan</dt>
                                        <dd class="font-semibold text-[#0A0F1F]">@rupiah($serviceFee)</dd>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between gap-3" x-show="offer.discount > 0" @if ($first['discount'] === 0) style="display:none" @endif>
                                    <dt class="flex items-center gap-2 text-[#6B7280]">Diskon <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200" x-text="offer.discount + '%'">{{ $first['discount'] }}%</span></dt>
                                    <dd class="font-semibold text-emerald-700" x-text="'- ' + rp(offer.list - offer.final)">- @rupiah($first['list_price'] - $first['final_price'])</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-[#6B7280]">Voucher</dt>
                                    <dd class="font-semibold text-emerald-700" :class="{ 'br-bump': bump }" x-text="voucherDiscount > 0 ? '- ' + rp(voucherDiscount) : '–'">–</dd>
                                </div>
                            </dl>
                            <div class="mt-5 flex items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-[#FEF3C7] via-white to-[#FEF3C7]/60 px-4 py-4 ring-1 ring-inset ring-[#F59E0B]/30 sm:px-5">
                                <span class="text-base font-bold text-[#0A0F1F] sm:text-lg">Total</span>
                                <span class="text-2xl font-extrabold tracking-tight tabular-nums text-[#0A0F1F]" :class="{ 'br-bump': bump }" x-text="rp(total)">@rupiah($first['final_price'] + $serviceFee)</span>
                            </div>
                        </div>

                        <p x-show="saved > 0" x-cloak class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-50 px-4 py-4 text-center text-sm font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200 sm:px-5">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 12 22l-9-9V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
                            <span>Anda hemat <span class="font-extrabold" x-text="rp(saved)"></span> jika order sekarang</span>
                        </p>

                        {{-- CTA --}}
                        <div class="flex flex-col gap-3 pt-1 sm:flex-row">
                            <button id="p-buy" type="submit" @disabled($stock === 0) :disabled="submitting || @js($stock === 0)"
                                class="btn-shine flex-1 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-6 py-4 text-center text-base font-bold text-[#0A0F1F] shadow-[0_8px_20px_rgba(245,158,11,0.3)] transition hover:shadow-[0_10px_26px_rgba(245,158,11,0.4)] hover:brightness-105 active:scale-[0.99] disabled:cursor-not-allowed disabled:from-slate-200 disabled:to-slate-200 disabled:text-slate-500 disabled:shadow-none">
                                {{ $stock > 0 ? 'Beli Sekarang' : 'Stok habis' }}
                            </button>
                            <a href="{{ \App\Support\Whatsapp::url('Halo ProAkses, saya mau tanya '.$product->name) }}" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-5 py-4 text-sm font-semibold text-[#374151] transition hover:border-[#F59E0B]">
                                <x-store.brand-icon name="whatsapp" class="text-base text-[#25D366]" />
                                Tanya admin
                            </a>
                        </div>
                        <p x-show="notice" x-cloak x-text="notice" role="status" class="rounded-xl bg-amber-50 px-4 py-3 text-center text-sm font-medium text-amber-800 ring-1 ring-inset ring-amber-200"></p>
                    </form>

                    {{-- Bilah beli menempel (HP) --}}
                    <div id="buy-bar" class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-5 py-3 shadow-[0_-4px_20px_rgba(15,23,42,0.08)] backdrop-blur md:hidden" x-data="{ show: false }" x-show="show" x-cloak
                        @scroll.window.passive="show = window.scrollY > 500">
                        <div class="flex items-center gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-semibold text-slate-900">{{ $product->name }}</p>
                                <p class="text-[13px] font-bold text-[#0A0F1F]" x-text="rp(total) + ' / ' + offer.title">@rupiah($first['final_price'] + $serviceFee)</p>
                            </div>
                            <a href="#buy-form" class="ml-auto shrink-0 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-5 py-2.5 text-sm font-bold text-[#0A0F1F] shadow-md shadow-[#F59E0B]/30 transition hover:brightness-105 active:scale-[0.99]">Beli</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FAQ singkat (dikelola di admin) --}}
            @if ($faqs->isNotEmpty())
            <div class="mt-14 max-w-3xl">
                <h2 class="text-lg font-bold text-[#0A0F1F]">Pertanyaan umum</h2>
                <div class="mt-4">
                    @foreach ($faqs as $i => $faq)
                        <details @if ($i === 0) open @endif class="group border-b border-[#E5E7EB] py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold text-[#0A0F1F] [&::-webkit-details-marker]:hidden">
                                {{ $faq->question }}
                                <span class="ml-4 shrink-0 text-[#9CA3AF] transition group-open:rotate-45">+</span>
                            </summary>
                            <p class="mt-3 text-sm text-[#374151]">{!! nl2br(e($faq->answer)) !!}</p>
                        </details>
                    @endforeach
                </div>
                <p class="mt-4 text-sm"><a href="{{ route('faq') }}" class="font-semibold text-[#B45309] transition hover:opacity-80">Lihat semua pertanyaan</a></p>
            </div>
            @endif

            {{-- Produk terkait --}}
            @if ($related->isNotEmpty())
                <div class="mt-16">
                    <div class="flex items-end justify-between">
                        <h2 class="text-lg font-bold text-[#0A0F1F]">Produk terkait</h2>
                        <a href="{{ route('products.index') }}" class="text-sm font-semibold text-[#B45309] transition hover:opacity-80">Lihat semua</a>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($related as $item)
                            <x-store.product-card :product="$item" />
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        // Panel beli: durasi, garansi, voucher, dan biaya layanan memengaruhi rincian harga. Potongan voucher dihitung server;
        // angka akhir pesanan dihitung ulang di server saat checkout.
        function productBuy(data) {
            const emailRe = /^[^@\s]+@[^@\s]+\.[^@\s]+$/
            const waRe = /^[0-9+][0-9\s-]{7,}$/

            return {
                options: data.options,
                warranties: data.warranties,
                optIndex: 0,
                warrantyId: data.warranties.length ? String(data.warranties[0].id) : '',
                name: data.prefill.name,
                email: data.prefill.email,
                wa: data.prefill.wa,
                voucherInput: '',
                voucherCode: '',
                voucherDiscount: 0,
                voucherMsg: '',
                voucherOk: false,
                checking: false,
                bump: false,
                error: '',
                notice: '',
                submitting: false,

                init() {
                    // Ganti durasi: potongan voucher yang terpasang dihitung ulang di server.
                    this.$watch('optIndex', () => { if (this.voucherCode) this.applyCode(this.voucherCode) })
                },
                get offer() { return this.options[this.optIndex] ?? this.options[0] },
                get warranty() { return this.warranties.find((w) => String(w.id) === String(this.warrantyId)) ?? null },
                get total() { return Math.max(0, this.offer.final + (this.warranty ? this.warranty.price : 0) + data.fee - this.voucherDiscount) },
                get saved() { return this.offer.list - this.offer.final + this.voucherDiscount },
                rp(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(n) },

                clearVoucher() {
                    this.voucherInput = ''
                    this.voucherCode = ''
                    this.voucherDiscount = 0
                    this.voucherMsg = ''
                },
                onVoucherInput() {
                    const code = this.voucherInput.trim().toUpperCase()
                    if (!code) return this.clearVoucher()
                    if (this.voucherCode && code !== this.voucherCode) {
                        this.voucherCode = ''
                        this.voucherDiscount = 0
                        this.voucherMsg = ''
                    }
                },
                async applyCode(raw) {
                    const code = String(raw || '').trim().toUpperCase()
                    if (!code) return this.clearVoucher()

                    this.checking = true
                    try {
                        const res = await fetch('/produk/' + data.slug + '/voucher', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            },
                            body: JSON.stringify({ code, option: this.optIndex }),
                        })
                        if (res.status === 429) throw new Error('Terlalu banyak percobaan. Coba lagi sebentar.')
                        const json = await res.json()
                        this.voucherOk = Boolean(json.ok)
                        this.voucherMsg = json.message || 'Kode voucher tidak valid.'
                        this.voucherCode = json.ok ? json.code : ''
                        this.voucherDiscount = json.ok ? json.discount : 0
                        this.voucherInput = code
                        if (json.ok) {
                            this.bump = false
                            this.$nextTick(() => { this.bump = true; setTimeout(() => (this.bump = false), 500) })
                        }
                    } catch (e) {
                        this.voucherOk = false
                        this.voucherCode = ''
                        this.voucherDiscount = 0
                        this.voucherMsg = e.message || 'Gagal memeriksa voucher. Coba lagi.'
                    } finally {
                        this.checking = false
                    }
                },

                submit(form) {
                    this.notice = ''
                    const name = this.name.trim(), email = this.email.trim(), wa = this.wa.trim()
                    let msg = ''
                    if (!name || !email || !wa) msg = 'Mohon lengkapi nama, email, dan nomor WhatsApp.'
                    else if (!emailRe.test(email)) msg = 'Format email belum benar.'
                    else if (!waRe.test(wa)) msg = 'Nomor WhatsApp belum benar.'
                    else if (this.voucherInput.trim() && !this.voucherCode) msg = 'Klik Pakai untuk memasang voucher, atau kosongkan kolom voucher.'
                    this.error = msg
                    if (msg) return

                    // Pembelian butuh akun: tamu masuk dulu, lalu kembali ke halaman produk ini.
                    if (!data.auth) {
                        this.notice = 'Masuk dulu untuk melanjutkan pembelian...'
                        window.location.href = '/produk/' + data.slug + '/beli'
                        return
                    }

                    this.submitting = true
                    form.submit()
                },
            }
        }
    </script>
@endpush
