@extends('layouts.store')

@php
    use App\Enums\OrderStatus;

    $initials = collect(preg_split('/\s+/', trim($user->name), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $memberMonths = max(0, (int) $user->created_at->diffInMonths(now()));

    // Ikon kecil yang dipakai ulang di beberapa tab.
    $ico = [
        'cal' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
        'hash' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/></svg>',
        'qr' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="5" height="5" rx="1"/><rect x="16" y="3" width="5" height="5" rx="1"/><rect x="3" y="16" width="5" height="5" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3M21 21v.01M12 7v3a2 2 0 0 1-2 2H7M3 12h.01M12 3h.01M12 16v.01M16 12h1M21 12v.01M12 21v-1"/></svg>',
        'check' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>',
        'clock' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'x' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
    ];

    // Tampilan status pesanan untuk pelanggan: [teks, kelas badge, kunci ikon].
    $statusView = fn (OrderStatus $s) => match ($s) {
        OrderStatus::Completed => ['Selesai', 'success', 'check'],
        OrderStatus::Paid => ['Berjalan', 'success', 'check'],
        OrderStatus::Verifying => ['Diproses', 'warn', 'clock'],
        OrderStatus::Pending => ['Pending', 'danger', 'clock'],
        OrderStatus::Expired => ['Kedaluwarsa', 'danger', 'x'],
        OrderStatus::Rejected => ['Ditolak', 'danger', 'x'],
        OrderStatus::Cancelled => ['Dibatalkan', 'danger', 'x'],
    };
    // Tampilan status transaksi: Berhasil, Menunggu, atau Gagal.
    $txView = fn (OrderStatus $s) => match (true) {
        $s === OrderStatus::Paid || $s === OrderStatus::Completed => ['Berhasil', 'success', 'check'],
        $s->holdsResources() => ['Menunggu', 'warn', 'clock'],
        default => ['Gagal', 'danger', 'x'],
    };

    $reportStatuses = ['open' => ['Menunggu', 'warn', 'clock'], 'resolved' => ['Selesai', 'success', 'check']];
    $waUrl = \App\Support\Whatsapp::url();

    // Form yang barusan dikirim (untuk membuka ulang modalnya saat validasi gagal).
    $failedForm = old('_form');
    $modalState = [
        'tab' => $tab,
        'review' => $failedForm === 'review' ? ['open' => true, 'orderId' => (string) old('_order'), 'rating' => (int) old('rating'), 'name' => (string) old('_name')] : null,
    ];
@endphp

@section('title', 'Profil Saya — '.\App\Support\StoreProfile::name())

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <main id="main" class="pf-page" x-data="profilePage(@js($modalState))">
        {{-- Banner profil + kartu statistik --}}
        <section class="bg-white pb-8 pt-8 sm:pb-10 sm:pt-10">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#0A0F1F] via-[#131A33] to-[#1A1B3A] shadow-xl shadow-slate-300/40">
                    <div class="pointer-events-none absolute -right-8 -top-10 h-36 w-36 rounded-full bg-[#F59E0B]/15"></div>
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-black/0 via-black/5 to-black/15"></div>
                    <div class="relative flex flex-col items-center gap-5 px-6 py-6 text-center sm:flex-row sm:items-center sm:text-left">
                        @if ($user->avatar)
                            <img src="{{ $user->avatar }}" alt="" referrerpolicy="no-referrer" class="h-20 w-20 shrink-0 rounded-full border-4 border-white object-cover shadow-lg shadow-black/10" />
                        @else
                            <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-4 border-white bg-white/20 text-xl font-bold text-white shadow-lg shadow-black/10">{{ $initials }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                                <h1 class="text-2xl font-bold tracking-tight text-white [text-shadow:0_1px_4px_rgb(0_0_0_/_0.15)]">{{ $user->name }}</h1>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-700 px-3 py-1 text-xs font-semibold text-white shadow-sm shadow-black/10 ring-1 ring-white/40">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 10.7a1 1 0 1 1 1.4-1.4l3.1 3.1 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                    Member Aktif
                                </span>
                            </div>
                            <div class="mt-2.5 flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white ring-1 ring-white/10 sm:text-sm">{{ $user->email }}</span>
                                @if ($user->phone)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white ring-1 ring-white/10 sm:text-sm">{{ $user->phone }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($user->is_admin)
                            <a href="{{ route('admin.dashboard') }}" class="rounded-full bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-5 py-2 text-sm font-semibold text-[#0A0F1F]">Panel Admin</a>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3 lg:gap-5">
                    @foreach ([
                        ['cyan', 'Total Pesanan', number_format($stats['orders'], 0, ',', '.'), $stats['success'].' berhasil', '<path d="M21 8a1 1 0 0 0-.5-.87l-8-4.57a1 1 0 0 0-1 0l-8 4.57A1 1 0 0 0 3 8v8a1 1 0 0 0 .5.87l8 4.57a1 1 0 0 0 1 0l8-4.57A1 1 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>'],
                        ['blue', 'Total Transaksi', 'Rp'.number_format($stats['spent'], 0, ',', '.'), 'dari pesanan yang berhasil', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'],
                        ['purple', 'Member Sejak', $user->created_at->translatedFormat('M Y'), $memberMonths > 0 ? $memberMonths.' bulan bersama ProAkses' : 'Baru bergabung', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>'],
                    ] as [$tone, $label, $value, $sub, $icon])
                        <div class="stat-card stat-card--{{ $tone }}">
                            <span class="stat-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg></span>
                            <div class="stat-text">
                                <p class="stat-label">{{ $label }}</p>
                                <p class="stat-value">{{ $value }}</p>
                                <p class="stat-trend">{{ $sub }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Navigasi tab --}}
        <div class="sticky top-16 z-40 border-b border-[#E5E7EB] bg-white/90 backdrop-blur-md">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <nav aria-label="Tab profil" class="flex gap-2 overflow-x-auto py-3" role="tablist">
                    @foreach ([
                        'profil' => ['Profil', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
                        'pesanan' => ['Pesanan Saya', '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73Z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/>'],
                        'transaksi' => ['Transaksi', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'],
                        'voucher' => ['Voucher', '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/>'],
                        'laporan' => ['Laporan', '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1Z"/><path d="M4 22v-7"/>'],
                        'pengaturan' => ['Pengaturan', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>'],
                    ] as $key => [$label, $icon])
                        <button type="button" role="tab" class="tab-btn" :data-active="tab === '{{ $key }}' ? 'true' : 'false'" :aria-selected="tab === '{{ $key }}'" @click="go('{{ $key }}')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>{{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-12">
            <div x-cloak x-show="tab === 'profil'" class="grid gap-8 lg:grid-cols-5" role="tabpanel">@include('store.profile.profil')</div>
            <div x-cloak x-show="tab === 'pesanan'" role="tabpanel">@include('store.profile.pesanan')</div>
            <div x-cloak x-show="tab === 'transaksi'" role="tabpanel">@include('store.profile.transaksi')</div>
            <div x-cloak x-show="tab === 'voucher'" role="tabpanel">@include('store.profile.voucher')</div>
            <div x-cloak x-show="tab === 'laporan'" role="tabpanel">@include('store.profile.laporan')</div>
            <div x-cloak x-show="tab === 'pengaturan'" class="max-w-2xl" role="tabpanel">@include('store.profile.pengaturan')</div>
        </div>

        @include('store.profile.modals')
    </main>
@endsection

@push('scripts')
    <script>
        function profilePage(state) {
            return {
                tab: state.tab,
                review: state.review ?? { open: false, orderId: '', rating: 0, name: '' },
                hover: 0,
                go(tab) {
                    this.tab = tab
                    const url = new URL(window.location)
                    url.searchParams.set('tab', tab)
                    history.replaceState(null, '', url)
                },
                openReview(orderId, name) {
                    this.review = { open: true, orderId, rating: 0, name }
                    this.hover = 0
                },
            }
        }
    </script>
@endpush
