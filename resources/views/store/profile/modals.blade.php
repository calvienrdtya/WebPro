@php
    $field = 'w-full rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-2.5 text-[15px] text-[#0A0F1F] placeholder:text-[#9CA3AF] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.1)] focus:outline-none';
    $reviewFailed = old('_form') === 'review';
@endphp

{{-- Modal tulis ulasan --}}
<div x-show="review.open" x-cloak @keydown.escape.window="review.open = false" class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-5" role="dialog" aria-modal="true" aria-labelledby="review-title">
    <div class="om-overlay absolute inset-0" @click="review.open = false"></div>
    <div class="om-card relative w-full">
        <div class="om-head">
            <span class="om-head-ic" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/></svg></span>
            <h3 id="review-title" class="om-title">Tulis Ulasan</h3>
            <button type="button" class="om-x" aria-label="Tutup" @click="review.open = false"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" :action="'{{ url('/pesanan') }}/' + review.orderId + '/ulasan'" class="mt-4 space-y-4" novalidate x-ref="reviewForm" @submit="if (!review.rating) { $event.preventDefault(); $refs.ratingError.hidden = false }">
            @csrf
            <input type="hidden" name="_form" value="review">
            <input type="hidden" name="_order" :value="review.orderId">
            <input type="hidden" name="_name" :value="review.name">
            <p class="text-sm text-[#6B7280]">Ulasan untuk <span class="font-semibold text-[#0A0F1F]" x-text="review.name"></span>. Ulasan tampil di halaman produk setelah disetujui admin.</p>
            <fieldset>
                <legend class="mb-2 text-sm font-medium text-[#374151]">Beri bintang</legend>
                <div class="flex items-center gap-1" @mouseleave="hover = 0">
                    @foreach (range(1, 5) as $n)
                        <button type="button" class="rounded-md p-1 transition hover:scale-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#F59E0B]" aria-label="{{ $n }} bintang" :aria-pressed="review.rating === {{ $n }}"
                            @mouseenter="hover = {{ $n }}" @click="review.rating = {{ $n }}; $refs.ratingError.hidden = true">
                            <svg width="30" height="30" viewBox="0 0 24 24" stroke-width="1.5" stroke-linejoin="round" :fill="(hover || review.rating) >= {{ $n }} ? '#F59E0B' : 'none'" :stroke="(hover || review.rating) >= {{ $n }} ? '#F59E0B' : '#D1D5DB'" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/></svg>
                        </button>
                    @endforeach
                    <span class="ms-2 text-sm font-semibold text-[#B45309]" x-text="review.rating ? review.rating + '/5' : ''"></span>
                </div>
                <input type="hidden" name="rating" :value="review.rating">
                <p x-ref="ratingError" @if (! ($reviewFailed && $errors->has('rating'))) hidden @endif class="mt-1 text-xs font-medium text-rose-700">Pilih jumlah bintang dulu.</p>
            </fieldset>
            <div>
                <label for="review-body" class="mb-1.5 block text-sm font-medium text-[#374151]">Ulasan kamu</label>
                <textarea id="review-body" name="body" rows="4" maxlength="1000" required placeholder="Ceritakan pengalamanmu memakai akun ini..." class="{{ $field }}">{{ $reviewFailed ? old('body') : '' }}</textarea>
                @if ($reviewFailed) @error('body') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror @endif
            </div>
            <button type="submit" class="pf-save w-full">Kirim Ulasan</button>
        </form>
    </div>
</div>
