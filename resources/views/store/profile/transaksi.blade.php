@php
    $txPills = [
        'all' => ['Semua', '<path d="m12 2 10 5-10 5L2 7Z"/><path d="m2 12 10 5 10-5"/><path d="m2 17 10 5 10-5"/>'],
        'selesai' => ['Berhasil', '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>'],
        'menunggu' => ['Menunggu', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'gagal' => ['Gagal', '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>'],
    ];
    $txUrl = fn ($key) => route('profil', array_filter(['tab' => 'transaksi', 'tx' => $key === 'all' ? null : $key, 'qtx' => $txSearch ?: null]));
@endphp

<div class="flex items-center gap-3">
    <span class="pf-ic h-9 w-9" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
    <div>
        <h2 class="text-xl font-bold tracking-tight text-[#0A0F1F] sm:text-2xl">Riwayat Transaksi</h2>
        <p class="mt-0.5 text-sm text-[#6B7280]">Semua transaksi kamu tercatat di sini.</p>
    </div>
</div>

<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    @foreach ([
        ['navy', 'Total Transaksi', $stats['orders'], '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
        ['success', 'Berhasil', $stats['success'], '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>'],
        ['warn', 'Menunggu', $stats['waiting'], '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        ['danger', 'Gagal', $stats['failed'], '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>'],
    ] as $n => [$tone, $label, $value, $icon])
        <div class="tx-sum tx-sum--{{ $tone }}" style="--d:{{ $n * 0.06 }}s">
            <span class="tx-sum-ic" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg></span>
            <p class="tx-sum-l">{{ $label }}</p>
            <p class="tx-sum-v">{{ number_format($value, 0, ',', '.') }}</p>
        </div>
    @endforeach
</div>

<div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="po-seg" role="group" aria-label="Filter status transaksi">
        @foreach ($txPills as $key => [$label, $icon])
            <a href="{{ $txUrl($key) }}" class="tx-filter filter-btn" data-active="{{ $txFilter === $key ? 'true' : 'false' }}" @if ($txFilter === $key) aria-current="true" @endif>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>{{ $label }}
            </a>
        @endforeach
    </div>
    <form method="GET" action="{{ route('profil') }}" class="relative w-full sm:w-[300px]" role="search">
        <input type="hidden" name="tab" value="transaksi">
        @if ($txFilter !== 'all') <input type="hidden" name="tx" value="{{ $txFilter }}"> @endif
        <label for="tx-search" class="sr-only">Cari transaksi</label>
        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-[#6B7280]"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
        <input id="tx-search" name="qtx" value="{{ $txSearch }}" type="search" placeholder="Cari transaksi..." autocomplete="off" class="po-search" />
    </form>
</div>

@if ($transactions->isEmpty())
    <div class="mt-5 flex flex-col items-center rounded-3xl border border-dashed border-[#D1D5DB] px-6 py-14 text-center">
        <p class="text-lg font-semibold text-[#0A0F1F]">{{ $txFilter !== 'all' || $txSearch !== '' ? 'Transaksi tidak ditemukan' : 'Belum ada transaksi' }}</p>
        <p class="mt-1 max-w-sm text-sm text-[#6B7280]">{{ $txFilter !== 'all' || $txSearch !== '' ? 'Coba ubah filter atau kata kunci pencarian.' : 'Transaksi kamu akan muncul di sini setelah checkout.' }}</p>
        <a href="{{ route('products.index') }}" class="mt-5 inline-flex min-h-[44px] items-center justify-center rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-6 text-sm font-semibold text-[#0A0F1F]">Lihat Produk</a>
    </div>
@else
    <ul class="mt-5 space-y-3">
        @foreach ($transactions as $i => $o)
            @php
                [$label, $tone, $iconKey] = $txView($o->status);
                $title = $o->product_name.' — '.($o->variant_title ?? '1 Bulan');
            @endphp
            <li class="tx-card" aria-label="Transaksi {{ $title }}, Rp{{ number_format($o->total, 0, ',', '.') }}, status {{ $label }}" style="--d:{{ min($i, 8) * 0.06 }}s">
                @if ($o->product?->image_url)
                    <img src="{{ $o->product->image_url }}" alt="{{ $title }}" class="tx-thumb" loading="lazy" />
                @else
                    <span class="tx-thumb bg-slate-100" aria-hidden="true"></span>
                @endif
                <div class="min-w-0">
                    <h3 class="tx-name">{{ $title }}</h3>
                    <p class="tx-meta"><span>{!! $ico['cal'] !!}{{ $o->created_at->translatedFormat('d M Y') }}</span><span class="tx-m">{!! $ico['qr'] !!}QRIS</span><span>{!! $ico['hash'] !!}{{ $o->code }}</span></p>
                </div>
                <div class="tx-side">
                    <span class="tx-amt">Rp{{ number_format($o->total, 0, ',', '.') }}</span>
                    <span class="po-badge po-badge--{{ $tone }}" aria-label="Status: {{ $label }}">{!! $ico[$iconKey] !!}{{ $label }}</span>
                </div>
            </li>
        @endforeach
    </ul>

    <div class="mt-6 flex flex-col items-center justify-between gap-3 sm:mt-7 sm:flex-row">
        <p class="text-[13px] text-[#6B7280] sm:text-sm">Menampilkan {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} dari {{ $transactions->total() }} transaksi</p>
        {{ $transactions->onEachSide(1)->links('store.partials.pagination') }}
    </div>
@endif
