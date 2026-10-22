{{-- Toast global: pesan flash dari server dan kejadian "toast" dari halaman (mis. salin kode). --}}
@php
    $flash = collect(['success', 'info', 'error'])->mapWithKeys(fn ($k) => [$k => session($k)])->filter()->first();
    $flashKind = collect(['success', 'info', 'error'])->first(fn ($k) => session($k));
@endphp
<div x-data="{ show: false, msg: '', error: false, timer: null,
        open(text, error = false) { this.msg = text; this.error = error; this.show = true; clearTimeout(this.timer); this.timer = setTimeout(() => this.show = false, 3200) } }"
     @toast.window="open($event.detail)"
     @if ($flash) x-init="$nextTick(() => open(@js($flash), {{ $flashKind === 'error' ? 'true' : 'false' }}))" @endif
     x-show="show" x-cloak x-transition.opacity.duration.250ms
     role="status" aria-live="polite"
     class="fixed bottom-6 left-1/2 z-[80] flex max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-2 rounded-2xl border bg-[#0A0F1F] px-4 py-3 text-sm font-medium text-white shadow-[0_12px_30px_rgba(10,15,31,0.35)]"
     :class="error ? 'border-red-400/50' : 'border-[#F59E0B]/40'">
    <svg x-show="!error" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FBBF24" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
    <svg x-show="error" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F87171" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
    <span x-text="msg"></span>
</div>
