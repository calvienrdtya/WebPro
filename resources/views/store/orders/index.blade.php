@extends('layouts.store')

@php
    use App\Enums\OrderStatus;

    $badge = fn (OrderStatus $s) => match ($s) {
        OrderStatus::Pending, OrderStatus::Verifying => 'bg-amber-50 text-amber-700 ring-amber-200',
        OrderStatus::Paid => 'bg-sky-50 text-sky-700 ring-sky-200',
        OrderStatus::Completed => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        default => 'bg-slate-100 text-slate-600 ring-slate-200',
    };
@endphp

@section('title', 'Pesanan Saya — '.\App\Support\StoreProfile::name())

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <main id="main" class="co-page mx-auto max-w-3xl px-5 py-8 sm:px-8 sm:py-12">
        <div class="mb-8">
            <h1 class="text-[28px] font-bold tracking-tight text-[#0A0F1F] sm:text-[32px]">Pesanan Saya</h1>
            <p class="mt-1 text-sm text-[#6B7280] sm:text-[15px]">Riwayat pesanan dan akun yang sudah Anda beli.</p>
        </div>

        @if ($orders->isEmpty())
            <div class="co-card text-center">
                <p class="text-base font-semibold text-[#0A0F1F]">Belum ada pesanan</p>
                <p class="mt-1 text-sm text-[#6B7280]">Pesanan Anda akan muncul di sini setelah checkout.</p>
                <a href="{{ route('products.index') }}" class="btn-shine cta mx-auto mt-5 max-w-xs">Lihat produk</a>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($orders as $o)
                    <li>
                        <a href="{{ route('orders.show', $o) }}" class="co-card flex items-center gap-4 !p-4 sm:!p-5">
                            <div class="co-thumb !h-14 !w-14">
                                @if ($o->product?->image_url)
                                    <img src="{{ $o->product->image_url }}" alt="" />
                                @else
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56'%3E%3Crect width='56' height='56' fill='%23f1f5f9'/%3E%3C/svg%3E" alt="" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-[#0A0F1F]">{{ $o->product_name }} · {{ $o->variant_title ?? '1 Bulan' }}</p>
                                <p class="mt-0.5 text-xs text-[#6B7280]">{{ $o->code }} · {{ $o->created_at->translatedFormat('j M Y, H:i') }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-bold tabular-nums text-[#0A0F1F]">@rupiah($o->total)</p>
                                <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 ring-inset {{ $badge($o->status) }}">{{ $o->status->label() }}</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </main>
@endsection
