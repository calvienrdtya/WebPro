<div>
    <h2 class="text-xl font-bold tracking-tight text-[#0A0F1F] sm:text-2xl">Voucher Saya</h2>
    <p class="mt-1 text-sm text-[#6B7280] sm:text-[15px]">Voucher yang sudah kamu klaim atau pakai.</p>
</div>

<div class="mt-6 flex flex-wrap gap-2" role="group" aria-label="Filter voucher">
    @foreach (['avail' => 'Tersedia', 'used' => 'Terpakai', 'expired' => 'Kadaluarsa'] as $key => $label)
        <a href="{{ route('profil', ['tab' => 'voucher', 'vc' => $key]) }}" class="vc-pill inline-flex items-center" aria-pressed="{{ $voucherFilter === $key ? 'true' : 'false' }}">{{ $label }} <span>{{ $vouchers['counts'][$key] }}</span></a>
    @endforeach
</div>

@if (count($vouchers['items']) === 0)
    <div class="mt-6 flex flex-col items-center rounded-3xl border border-dashed border-[#D1D5DB] px-6 py-14 text-center">
        <p class="text-lg font-semibold text-[#0A0F1F]">Belum ada voucher</p>
        <p class="mt-1 max-w-sm text-sm text-[#6B7280]">Voucher akan muncul di sini setelah kamu klaim di halaman Voucher.</p>
        <a href="{{ route('vouchers.index') }}" class="mt-5 inline-flex min-h-[44px] items-center justify-center rounded-2xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-6 text-sm font-semibold text-[#0A0F1F]">Cari Voucher</a>
    </div>
@else
    <div class="mt-6 grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2 xl:gap-5">
        @foreach ($vouchers['items'] as $i => $v)
            @include('store.partials.voucher-card', ['v' => $v, 'i' => $i])
        @endforeach
    </div>
@endif
