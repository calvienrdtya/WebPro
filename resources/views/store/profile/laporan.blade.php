<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-xl font-bold tracking-tight text-[#0A0F1F] sm:text-2xl">Laporan Saya</h2>
        <p class="mt-1 text-sm text-[#6B7280] sm:text-[15px]">Ada kendala dengan akun atau pesanan? Kirim laporan dan admin akan menindaklanjutinya.</p>
    </div>
    <a href="{{ route('reports.create') }}" class="pf-save shrink-0">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Buat Laporan
    </a>
</div>

@if ($reports->isEmpty())
    <div class="mt-6 flex flex-col items-center rounded-3xl border border-dashed border-[#D1D5DB] px-6 py-14 text-center">
        <p class="text-lg font-semibold text-[#0A0F1F]">Belum ada laporan</p>
        <p class="mt-1 max-w-sm text-sm text-[#6B7280]">Laporan yang kamu kirim akan tampil di sini beserta statusnya.</p>
    </div>
@else
    <ul class="mt-6 space-y-3">
        @foreach ($reports as $i => $r)
            @php [$label, $tone, $iconKey] = $reportStatuses[$r->status]; @endphp
            <li class="tx-card" style="--d:{{ min($i, 8) * 0.06 }}s">
                <span class="tx-thumb flex items-center justify-center bg-[#F59E0B]/10 text-[#F59E0B]" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1Z"/><path d="M4 22v-7"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="tx-name">{{ $r->subject }}</h3>
                    <p class="tx-meta"><span>{!! $ico['cal'] !!}{{ $r->created_at->translatedFormat('d M Y, H:i') }}</span><span>{{ $r->category->label() }}</span></p>
                </div>
                <div class="tx-side">
                    <span class="po-badge po-badge--{{ $tone }}" aria-label="Status: {{ $label }}">{!! $ico[$iconKey] !!}{{ $label }}</span>
                </div>
            </li>
        @endforeach
    </ul>
@endif
