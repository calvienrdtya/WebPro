@extends('layouts.store')

@php
    use App\Enums\OrderStatus;

    $status = $order->status;
    $isPending = $status === OrderStatus::Pending;
    $isVerifying = $status === OrderStatus::Verifying;
    $isPaid = $order->isPaid();
    $isClosed = $status->isClosedUnpaid();
    $waLink = \App\Support\Whatsapp::url('Halo ProAkses, saya mau tanya pesanan '.$order->code);
    $image = $order->product?->image_url;
    $copyIcon = '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>';
@endphp

@section('title', 'Pesanan '.$order->code.' — '.\App\Support\StoreProfile::name())

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <main id="main" class="co-page mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12">
        <div class="mb-8 flex items-center gap-3 sm:gap-4">
            <span class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B] sm:flex" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </span>
            <div>
                <h1 class="text-[28px] font-bold tracking-tight text-[#0A0F1F] sm:text-[32px]">{{ $isPaid ? 'Pesanan Berhasil' : ($isClosed ? 'Pesanan '.$status->label() : 'Checkout') }}</h1>
                <p class="mt-1 text-sm text-[#6B7280] sm:text-[15px]">
                    Kode pesanan <span class="font-semibold text-[#0A0F1F]">{{ $order->code }}</span>
                    @if ($isPending) · Selesaikan pembayaran untuk mendapatkan akun premium Anda.
                    @elseif ($isVerifying) · Pembayaran Anda sedang diverifikasi admin.
                    @elseif ($isPaid) · Terima kasih, pembayaran sudah terverifikasi.
                    @endif
                </p>
            </div>
        </div>

        @unless ($isClosed)
            @php
                // Posisi tahap: 3 = pembayaran (menunggu bayar / verifikasi), 4 = selesai.
                $step = $isPaid ? 4 : 3;
                $steps = ['Pilih Produk', 'Isi Data', 'Pembayaran', 'Selesai'];
            @endphp
            <ol aria-label="Tahapan checkout" class="mb-8 hidden items-center sm:mb-10 sm:flex">
                @foreach ($steps as $i => $label)
                    @php($n = $i + 1)
                    @if ($i > 0)
                        <li class="step-line {{ $n <= $step ? 'step-line--done' : ($n === $step + 1 ? '' : '') }}" aria-hidden="true"></li>
                    @endif
                    <li class="flex items-center gap-2.5" @if ($n === $step && ! $isPaid) aria-current="step" @endif>
                        @if ($n < $step || ($n === $step && $isPaid))
                            <span class="step-dot step-dot--done"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 10.7a1 1 0 1 1 1.4-1.4l3.1 3.1 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg></span>
                        @elseif ($n === $step)
                            <span class="step-dot step-dot--active">{{ $n }}</span>
                        @else
                            <span class="step-dot step-dot--todo">{{ $n }}</span>
                        @endif
                        <span class="text-sm {{ $n === $step ? 'font-bold text-[#0A0F1F]' : ($n < $step ? 'font-semibold text-[#0A0F1F]' : 'font-medium text-[#6B7280]') }}">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
            <div class="mb-6 sm:hidden" role="group" aria-label="Tahapan checkout">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-semibold text-[#0A0F1F]">Langkah {{ $step }} dari 4</span>
                    <span class="font-bold text-[#B45309]">{{ $steps[$step - 1] }}</span>
                </div>
                <div class="mt-2.5 flex gap-1.5" aria-hidden="true">
                    @foreach ($steps as $i => $label)
                        <span class="h-1.5 flex-1 rounded-full {{ $i + 1 < $step || $isPaid ? 'bg-emerald-500' : ($i + 1 === $step ? 'bg-gradient-to-r from-[#FBBF24] to-[#F59E0B]' : 'bg-[#E5E7EB]') }}"></span>
                    @endforeach
                </div>
            </div>
        @endunless

        <div class="grid gap-6 md:gap-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            {{-- Kiri: informasi pesanan (di HP tampil setelah kartu pembayaran) --}}
            <div class="order-2 lg:order-1">
                <div class="co-card" style="--delay:.1s">
                    @if ($isPaid)
                        <section class="co-section" aria-labelledby="h-akun">
                            <div class="co-head">
                                <span class="co-head-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 2-2 2m-7.6 7.6a5.5 5.5 0 1 1-7.8 7.8 5.5 5.5 0 0 1 7.8-7.8Zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></span>
                                <h2 id="h-akun">Akun Anda</h2>
                            </div>
                            <div class="space-y-3">
                                @foreach ($order->deliveries as $d)
                                    @php($acc = $d->account)
                                    <div class="rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-[#0A0F1F]">{{ $order->months > 1 ? 'Bulan ke-'.$d->sequence : $order->product_name }}</p>
                                            @if ($d->isDelivered())
                                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">Terkirim {{ $d->delivered_at->translatedFormat('j M Y') }}</span>
                                            @else
                                                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold text-amber-700 ring-1 ring-inset ring-amber-200">{{ $d->due_on && $d->due_on->lte(today()) ? 'Sedang disiapkan' : 'Menunggu jadwal' }}</span>
                                            @endif
                                        </div>
                                        @if ($d->isDelivered() && $acc)
                                            <dl class="mt-3 space-y-2 text-sm">
                                                @foreach ([['Email', $acc->email], ['Password', $acc->password], ['Profil', $acc->profile], ['PIN', $acc->pin]] as [$label, $value])
                                                    @continue(blank($value))
                                                    <div class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 ring-1 ring-inset ring-[#E5E7EB]">
                                                        <div class="min-w-0">
                                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[#6B7280]">{{ $label }}</dt>
                                                            <dd class="break-all font-medium text-[#0A0F1F]">{{ $value }}</dd>
                                                        </div>
                                                        <button type="button" aria-label="Salin {{ $label }}" onclick="copyText(@js((string) $value)).then(ok => window.dispatchEvent(new CustomEvent('toast', { detail: ok ? '{{ $label }} disalin' : 'Gagal menyalin' })))"
                                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-[#E5E7EB] text-[#6B7280] transition hover:border-[#F59E0B] hover:text-[#0A0F1F]">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $copyIcon !!}</svg>
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </dl>
                                            @if ($d->warranty_ends_on)
                                                <p class="mt-3 text-xs text-[#6B7280]">{{ $order->warranty_name }} berlaku sampai <span class="font-semibold text-[#0A0F1F]">{{ $d->warranty_ends_on->translatedFormat('j F Y') }}</span>.</p>
                                            @endif
                                        @elseif ($d->due_on)
                                            <p class="mt-2 text-sm text-[#6B7280]">
                                                @if ($d->due_on->lte(today()))
                                                    Akun bulan ini sedang disiapkan dan akan dikirim ke <span class="font-medium text-[#0A0F1F]">{{ $order->customer_email }}</span> secepatnya.
                                                @else
                                                    Akun bulan ini dikirim otomatis ke <span class="font-medium text-[#0A0F1F]">{{ $order->customer_email }}</span> pada {{ $d->due_on->translatedFormat('j F Y') }}.
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs text-[#6B7280]">Jangan ubah email, password, atau PIN akun, dan jangan bagikan ke orang lain. Kredensial juga dikirim ke {{ $order->customer_email }}.</p>
                        </section>
                    @endif

                    <section class="co-section" aria-labelledby="h-info">
                        <div class="co-head">
                            <span class="co-head-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                            <h2 id="h-info">Informasi Pemesanan</h2>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ([['Nama', $order->customer_name], ['Email', $order->customer_email], ['WhatsApp', $order->customer_phone]] as [$label, $value])
                                <div class="co-field">
                                    <p class="co-field-label">{{ $label }}</p>
                                    <p class="co-field-value" title="{{ $value }}">{{ $value }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="co-section" aria-labelledby="h-order">
                        <div class="co-head">
                            <span class="co-head-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span>
                            <h2 id="h-order">Detail Pesanan</h2>
                        </div>
                        <div class="flex items-center gap-4 sm:gap-5">
                            <div class="co-thumb">
                                @if ($image)
                                    <img src="{{ $image }}" alt="{{ $order->product_name }}" />
                                @else
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Crect width='80' height='80' fill='%23f1f5f9'/%3E%3C/svg%3E" alt="" />
                                @endif
                                <span class="co-thumb-badge">{{ $order->months }} akun</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-lg font-semibold tracking-tight text-[#0A0F1F]">{{ $order->product_name }}</h3>
                                <p class="mt-1 text-sm text-[#6B7280]">Durasi: {{ $order->variant_title ?? '1 Bulan' }}</p>
                                <p class="mt-0.5 text-sm text-[#6B7280]">Garansi: {{ $order->warranty_name ? $order->warranty_name.' ('.$order->warranty_days.' hari per akun)' : '-' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="co-section" aria-labelledby="h-price">
                        <div class="co-head">
                            <span class="co-head-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                            <h2 id="h-price">Rincian Harga</h2>
                        </div>
                        <div class="co-price">
                            <dl>
                                <div class="co-row"><dt>Harga produk</dt><dd>@rupiah($order->list_price)</dd></div>
                                <div class="co-row"><dt>Garansi</dt><dd>{{ $order->warranty_name ? ($order->warranty_price === 0 ? 'Gratis' : 'Rp '.number_format($order->warranty_price, 0, ',', '.')) : '-' }}</dd></div>
                                @if ($order->service_fee > 0)
                                    <div class="co-row"><dt>Biaya layanan</dt><dd>@rupiah($order->service_fee)</dd></div>
                                @endif
                                <div class="co-row co-row--save"><dt>Diskon</dt><dd>{{ $order->productDiscount() > 0 ? '- Rp '.number_format($order->productDiscount(), 0, ',', '.') : '–' }}</dd></div>
                                <div class="co-row co-row--save"><dt>Voucher <span class="text-xs font-medium text-[#6B7280]">{{ $order->voucher_code ? '('.$order->voucher_code.')' : '' }}</span></dt><dd>{{ $order->voucher_discount > 0 ? '- Rp '.number_format($order->voucher_discount, 0, ',', '.') : '–' }}</dd></div>
                            </dl>
                            <div class="co-dash" aria-hidden="true"></div>
                            <dl class="co-total"><dt>Total</dt><dd>@rupiah($order->total)</dd></dl>
                        </div>
                    </section>
                </div>
            </div>

            {{-- Kanan: pembayaran / status (di HP tampil paling atas) --}}
            <div class="order-1 lg:order-2">
                <div class="co-card lg:sticky lg:top-24" style="--delay:.2s">
                    @if ($isPending)
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B]">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                            </span>
                            <div>
                                <h2 class="text-lg font-semibold leading-tight tracking-tight text-[#0A0F1F]">Scan QRIS</h2>
                                <p class="text-xs text-[#6B7280]">Bayar dari e-wallet atau mobile banking</p>
                            </div>
                        </div>

                        <p class="mt-4 text-sm text-[#374151]">Scan kode QR di bawah ini, lalu bayar sebesar <span class="font-bold text-[#0A0F1F]">@rupiah($order->total)</span>.</p>

                        <div class="mt-4 flex justify-center">
                            <p class="timer" role="timer" aria-live="off" x-data="payTimer(@js($order->expires_at->getTimestamp() * 1000))" x-init="start()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/></svg>
                                <span x-show="!over">Bayar dalam <b x-text="label">15:00</b></span>
                                <span x-show="over" x-cloak>Waktu pembayaran habis</span>
                            </p>
                        </div>

                        <div class="qr-box">
                            <span class="qr-corner qr-corner--tl"></span><span class="qr-corner qr-corner--tr"></span>
                            <span class="qr-corner qr-corner--bl"></span><span class="qr-corner qr-corner--br"></span>
                            <img src="{{ $qris }}" alt="Kode QRIS pembayaran" />
                        </div>
                        <div class="flex justify-center">
                            <p class="qris-badge"><b>QRIS</b> Semua e-wallet &amp; mobile banking</p>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <button type="button" class="qr-action" onclick="copyText('{{ $order->total }}').then(ok => window.dispatchEvent(new CustomEvent('toast', { detail: ok ? 'Nominal @rupiah($order->total) disalin' : 'Gagal menyalin' })))">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $copyIcon !!}</svg>
                                Salin nominal
                            </button>
                            <a href="{{ $qris }}" download="qris-proakses" class="qr-action">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Unduh QR
                            </a>
                        </div>

                        <ul class="mt-5 space-y-2">
                            @foreach ([
                                ['Pembayaran diverifikasi admin setelah Anda menekan "Saya Sudah Bayar"', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>'],
                                ['Akun dikirim ke email setelah pembayaran terverifikasi', '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"/>'],
                                ['Garansi sesuai paket yang Anda pilih', '<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>'],
                            ] as [$text, $icon])
                                <li class="trust-item">
                                    <span class="trust-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg></span>
                                    {{ $text }}
                                </li>
                            @endforeach
                        </ul>

                        <form method="POST" action="{{ route('orders.claim', $order) }}" enctype="multipart/form-data" class="mt-5" x-data="{ busy: false, name: '' }" @submit="busy = true">
                            @csrf
                            <label class="block text-[13px] font-medium text-[#374151]" for="proof">Bukti transfer (opsional)</label>
                            <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" @change="name = $event.target.files[0]?.name ?? ''"
                                class="mt-1.5 block w-full rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-3 py-2 text-sm text-[#374151] file:mr-3 file:rounded-lg file:border-0 file:bg-[#F59E0B]/15 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-[#B45309]" />
                            @error('proof') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                            <button type="submit" :disabled="busy" class="btn-shine cta mt-4">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                Saya Sudah Bayar
                            </button>
                        </form>

                        <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-3 text-center" onsubmit="return confirm('Batalkan pesanan ini?')">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-[#6B7280] underline-offset-2 transition hover:text-rose-700 hover:underline">Batalkan pesanan</button>
                        </form>
                    @elseif ($isVerifying)
                        <div class="text-center" x-data="orderPoll(@js(route('orders.status', $order)), 'verifying')" x-init="start()">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B]">
                                <svg class="h-7 w-7 animate-spin" style="animation-duration:2s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.2-8.55"/></svg>
                            </span>
                            <h2 class="mt-4 text-lg font-semibold tracking-tight text-[#0A0F1F]">Menunggu verifikasi</h2>
                            <p class="mt-2 text-sm text-[#374151]">Pembayaran @rupiah($order->total) sedang dicek admin. Akun akan dikirim ke <span class="font-medium text-[#0A0F1F]">{{ $order->customer_email }}</span> dan muncul di halaman ini setelah terverifikasi.</p>
                            <p class="mt-2 text-xs text-[#6B7280]">Dikirim {{ $order->claimed_paid_at?->translatedFormat('j M Y, H:i') }}. Halaman ini diperbarui otomatis.</p>
                            <a href="{{ route('orders.index') }}" class="mt-5 block rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm font-semibold text-[#374151] transition hover:border-[#F59E0B]">Lihat pesanan saya</a>
                        </div>
                    @elseif ($isPaid)
                        <div class="text-center">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <h2 class="mt-4 text-lg font-semibold tracking-tight text-[#0A0F1F]">Pembayaran terverifikasi</h2>
                            <p class="mt-2 text-sm text-[#374151]">Akun sudah bisa dipakai. Detailnya ada di samping dan sudah dikirim ke email Anda.</p>
                            <a href="{{ route('orders.index') }}" class="btn-shine cta mt-5">Lihat pesanan saya</a>
                            <a href="{{ route('reports.create', ['pesanan' => $order->code]) }}" class="mt-3 block rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm font-semibold text-[#374151] transition hover:border-[#F59E0B]">Laporkan masalah</a>
                            <p class="mt-4 text-sm text-[#6B7280]">Butuh cepat? <a href="{{ $waLink }}" rel="noopener" class="font-semibold text-[#B45309] transition hover:opacity-80">Chat admin</a></p>
                        </div>
                    @else
                        <div class="text-center">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 text-rose-700">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                            </span>
                            <h2 class="mt-4 text-lg font-semibold tracking-tight text-[#0A0F1F]">Pesanan {{ mb_strtolower($status->label()) }}</h2>
                            <p class="mt-2 text-sm text-[#374151]">
                                @if ($status === OrderStatus::Expired)
                                    Waktu pembayaran habis dan stok sudah dilepas. Sudah terlanjur transfer? Hubungi admin dengan menyebut kode pesanan {{ $order->code }}, pesanan bisa dipulihkan selama stok masih ada.
                                @elseif ($status === OrderStatus::Rejected)
                                    Pembayaran tidak dapat diverifikasi.@if ($order->reject_reason) Alasan: {{ $order->reject_reason }}.@endif Hubungi admin bila Anda sudah membayar.
                                @else
                                    Pesanan ini sudah Anda batalkan.
                                @endif
                            </p>
                            <a href="{{ route('products.show', $order->product?->slug ?? '') }}" class="btn-shine cta mt-5">Pesan lagi</a>
                            <p class="mt-4 text-sm text-[#6B7280]">Butuh bantuan? <a href="{{ $waLink }}" rel="noopener" class="font-semibold text-[#B45309] transition hover:opacity-80">Chat admin</a></p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        // Hitung mundur ke batas bayar dari server; setelah habis halaman dimuat ulang agar status terbaru tampil.
        function payTimer(deadline) {
            return {
                label: '00:00',
                over: false,
                start() {
                    const tick = () => {
                        const left = Math.max(0, deadline - Date.now())
                        const m = Math.floor(left / 60000), s = Math.floor((left % 60000) / 1000)
                        this.label = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0')
                        if (left === 0) {
                            this.over = true
                            clearInterval(iv)
                            setTimeout(() => window.location.reload(), 1500)
                        }
                    }
                    const iv = setInterval(tick, 1000)
                    tick()
                },
            }
        }

        // Memuat ulang halaman begitu status pesanan berubah (mis. admin sudah memverifikasi).
        function orderPoll(url, current) {
            return {
                start() {
                    setInterval(async () => {
                        try {
                            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                            if (res.ok && (await res.json()).status !== current) window.location.reload()
                        } catch (e) {}
                    }, 15000)
                },
            }
        }
    </script>
@endpush
