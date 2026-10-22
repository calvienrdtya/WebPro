{{-- Kartu voucher: dipakai /voucher dan tab Voucher di profil. Variabel: $v (VoucherPresenter::present), $i (urutan). --}}
@php
    $icoCal = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>';
    $icoTag = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 12 22l-9-9V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>';
@endphp
<article class="vc {{ $v['state'] === 'expired' ? 'vc--expired' : '' }} {{ $v['state'] === 'used' ? 'vc--used' : '' }}" style="--d:{{ min($i, 8) * 0.06 }}s" aria-labelledby="vt-{{ $v['id'] }}">
    <div class="vc-left" aria-hidden="true">
        <span class="vc-val {{ strlen($v['value']) > 5 ? 'vc-val--sm' : '' }}">{{ $v['value'] }}</span>
        <span class="vc-lab">{{ $v['label'] }}</span>
    </div>
    <div class="vc-right">
        @if ($v['state'] === 'claimed')
            <span class="vc-badge vc-badge--ok">Siap Dipakai</span>
        @elseif ($v['state'] === 'used')
            <span class="vc-badge vc-badge--ok">Sudah Dipakai</span>
        @endif
        <h3 class="vc-title" id="vt-{{ $v['id'] }}">{{ $v['title'] }}</h3>
        @if ($v['description'])
            <p class="vc-desc">{{ $v['description'] }}</p>
        @endif
        <ul class="vc-meta">
            <li><span class="vc-mi">{!! $icoCal !!}</span>{{ $v['until'] }}</li>
            @foreach (array_filter([$v['min_purchase'], $v['max_discount'], $v['scope'], $v['remaining'] !== null ? 'Sisa kuota '.number_format($v['remaining'], 0, ',', '.') : null]) as $line)
                <li><span class="vc-mi">{!! $icoTag !!}</span>{{ $line }}</li>
            @endforeach
        </ul>

        <div class="vc-actions">
            <span class="vc-code" x-data="{ copied: false }">
                <code>{{ $v['code'] }}</code>
                <button type="button" class="vc-copy" aria-label="Salin kode voucher {{ $v['code'] }}"
                    @click="copyText(@js($v['code'])).then((done) => { copied = done; setTimeout(() => copied = false, 1500); $dispatch('toast', done ? @js('Kode '.$v['code'].' disalin') : @js('Salin manual: '.$v['code'])) })">
                    <svg x-show="!copied" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <svg x-show="copied" x-cloak width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                </button>
            </span>

            @switch ($v['state'])
                @case ('available')
                    @auth
                        <form method="POST" action="{{ route('vouchers.claim', $v['id']) }}">
                            @csrf
                            <button type="submit" class="vc-btn vc-btn--claim w-full" aria-label="Klaim voucher {{ $v['title'] }}">Klaim</button>
                        </form>
                    @else
                        <a href="{{ route('vouchers.claim', $v['id']) }}" class="vc-btn vc-btn--claim" aria-label="Masuk untuk klaim voucher {{ $v['title'] }}">Masuk untuk klaim</a>
                    @endauth
                    @break
                @case ('claimed')
                    <a href="{{ route('products.index') }}" class="vc-btn vc-btn--use" aria-label="Pilih produk untuk memakai voucher {{ $v['title'] }}">Pilih produk</a>
                    @break
                @case ('direct')
                    <p class="vc-note">Tanpa klaim: ketik kode ini langsung saat checkout.</p>
                    @break
                @case ('used')
                    <span class="vc-btn vc-btn--used" role="note">Terpakai</span>
                    @break
                @default
                    <span class="vc-btn vc-btn--expired" role="note">Kadaluarsa</span>
            @endswitch
        </div>
    </div>
</article>
