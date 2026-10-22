@php use App\Enums\OrderStatus; @endphp
@php
    $pills = [
        'all' => ['Semua', '<path d="m12 2 10 5-10 5L2 7Z"/><path d="m2 12 10 5 10-5"/><path d="m2 17 10 5 10-5"/>'],
        'selesai' => ['Selesai', '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>'],
        'diproses' => ['Diproses', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'pending' => ['Pending', '<path d="M6 2h12M6 22h12"/><path d="M7 2v4a5 5 0 0 0 2 4l3 2-3 2a5 5 0 0 0-2 4v4M17 2v4a5 5 0 0 1-2 4l-3 2 3 2a5 5 0 0 1 2 4v4"/>'],
        'gagal' => ['Gagal', '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>'],
    ];
    $pillUrl = fn ($key) => route('profil', array_filter(['tab' => 'pesanan', 'status' => $key === 'all' ? null : $key, 'q' => $orderSearch ?: null]));
@endphp

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="po-seg" role="group" aria-label="Filter status pesanan">
        @foreach ($pills as $key => [$label, $icon])
            <a href="{{ $pillUrl($key) }}" class="filter-btn" data-active="{{ $orderFilter === $key ? 'true' : 'false' }}" @if ($orderFilter === $key) aria-current="true" @endif>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>{{ $label }}
            </a>
        @endforeach
    </div>
    <form method="GET" action="{{ route('profil') }}" class="relative w-full sm:w-[300px]" role="search">
        <input type="hidden" name="tab" value="pesanan">
        @if ($orderFilter !== 'all') <input type="hidden" name="status" value="{{ $orderFilter }}"> @endif
        <label for="po-search" class="sr-only">Cari pesanan</label>
        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-[#6B7280]"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
        <input id="po-search" name="q" value="{{ $orderSearch }}" type="search" placeholder="Cari produk atau kode..." autocomplete="off" class="po-search" />
    </form>
</div>

@if ($orders->isEmpty())
    <div class="mt-6 flex flex-col items-center rounded-3xl border border-dashed border-[#D1D5DB] px-6 py-14 text-center">
        <span class="text-[#F59E0B] opacity-40"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8a1 1 0 0 0-.5-.87l-8-4.57a1 1 0 0 0-1 0l-8 4.57A1 1 0 0 0 3 8v8a1 1 0 0 0 .5.87l8 4.57a1 1 0 0 0 1 0l8-4.57A1 1 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg></span>
        <p class="mt-4 text-lg font-semibold text-[#0A0F1F]">{{ $orderFilter !== 'all' || $orderSearch !== '' ? 'Pesanan tidak ditemukan' : 'Belum ada pesanan' }}</p>
        <p class="mt-1 max-w-sm text-sm text-[#6B7280]">{{ $orderFilter !== 'all' || $orderSearch !== '' ? 'Coba ubah filter atau kata kunci pencarian.' : 'Pesanan kamu akan muncul di sini setelah checkout.' }}</p>
        <a href="{{ route('products.index') }}" class="mt-5 inline-flex min-h-[44px] items-center justify-center rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-6 text-sm font-semibold text-[#0A0F1F]">Lihat Produk</a>
    </div>
@else
    <div class="mt-6 space-y-3 sm:space-y-4">
        @foreach ($orders as $i => $o)
            @php
                [$label, $tone, $iconKey] = $statusView($o->status);
                $title = $o->product_name.' — '.($o->variant_title ?? '1 Bulan');
                $paid = $o->isPaid();
            @endphp
            <article class="po-card" aria-label="Pesanan {{ $title }}, status {{ $label }}" style="--d:{{ min($i, 8) * 0.07 }}s">
                @if ($o->product?->image_url)
                    <img src="{{ $o->product->image_url }}" alt="{{ $title }}" class="po-thumb" loading="lazy" />
                @else
                    <span class="po-thumb bg-slate-100" aria-hidden="true"></span>
                @endif
                <div class="po-info">
                    <h3 class="po-name">{{ $title }}</h3>
                    <p class="po-meta"><span>{!! $ico['cal'] !!}{{ $o->created_at->translatedFormat('d M Y') }}</span><span>{!! $ico['hash'] !!}{{ $o->code }}</span></p>
                    @if ($paid)
                        <p class="mt-1.5 text-xs">
                            @if ($o->review)
                                <span class="inline-flex items-center gap-1 font-medium text-[#B45309]">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/></svg>
                                    Ulasan {{ $o->review->rating }}/5 · {{ match ($o->review->status) { 'approved' => 'tampil di toko', 'hidden' => 'terkirim', default => 'menunggu persetujuan' } }}
                                </span>
                            @endif
                        </p>
                    @endif
                </div>
                <div class="po-act">
                    <span class="po-badge po-badge--{{ $tone }}" aria-label="Status: {{ $label }}">{!! $ico[$iconKey] !!}{{ $label }}</span>
                    <span class="po-price">Rp{{ number_format($o->total, 0, ',', '.') }}</span>
                    <a href="{{ route('orders.show', $o) }}" class="po-btn" aria-label="Lihat detail pesanan {{ $o->code }}, {{ $title }}">{{ $o->status === OrderStatus::Pending ? 'Lanjutkan Pembayaran' : ($paid ? 'Lihat Detail Akun' : 'Lihat Detail') }}</a>
                    @if ($paid || $o->status === OrderStatus::Verifying)
                        <div class="col-span-full flex flex-wrap gap-2">
                            @if ($paid && ! $o->review)
                                <button type="button" class="inline-flex min-h-[38px] flex-1 items-center justify-center gap-1.5 rounded-full border border-[#E5E7EB] bg-white px-4 text-[13px] font-semibold text-[#374151] transition hover:border-[#F59E0B]" @click="openReview(@js($o->code), @js($title))">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/></svg>Tulis Ulasan
                                </button>
                            @endif
                            <a href="{{ route('reports.create', ['pesanan' => $o->code]) }}" class="inline-flex min-h-[38px] flex-1 items-center justify-center gap-1.5 rounded-full border border-[#E5E7EB] bg-white px-4 text-[13px] font-semibold text-[#374151] transition hover:border-[#F59E0B]">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1Z"/><path d="M4 22v-7"/></svg>Laporkan
                            </a>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-6 flex flex-col items-center justify-between gap-3 sm:mt-7 sm:flex-row">
        <p class="text-[13px] text-[#6B7280] sm:text-sm">Menampilkan {{ $orders->firstItem() }}–{{ $orders->lastItem() }} dari {{ $orders->total() }} pesanan</p>
        {{ $orders->onEachSide(1)->links('store.partials.pagination') }}
    </div>
@endif
