@props([
    'id',
    'name',
    'label',
    'type' => 'text',
    'icon' => 'user',
    'placeholder' => '',
    'autocomplete' => null,
    'toggle' => false,
    'strength' => false,
    'minlength' => null,
])

@php
    $icons = [
        'user' => '<path d="M20 21a8 8 0 0 0-16 0" /><circle cx="12" cy="7" r="4" />',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="3" /><path d="m3 7 9 6 9-6" />',
        'lock' => '<rect x="4" y="11" width="16" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" />',
    ];
    $hasError = $errors->has($name);
@endphp

@if ($strength)
    @once
        <script>
            window.passwordMeter = function (p) {
                var s = 0;
                if (p.length >= 8) s++;
                if (/[A-Za-z]/.test(p) && /\d/.test(p)) s++;
                if (p.length >= 12 || (/[A-Z]/.test(p) && /[^A-Za-z0-9]/.test(p))) s++;
                return [
                    { width: '0%', color: '', label: '' },
                    { width: '33%', color: '#DC2626', label: 'Lemah' },
                    { width: '66%', color: '#F59E0B', label: 'Sedang' },
                    { width: '100%', color: '#10B981', label: 'Kuat' },
                ][p ? Math.max(s, 1) : 0];
            };
        </script>
    @endonce
@endif

<div @if ($strength) x-data="{ show: false, pw: '', get meter() { return passwordMeter(this.pw) } }" @elseif ($toggle) x-data="{ show: false }" @endif>
    <div class="mb-1.5 flex items-center justify-between">
        <label for="{{ $id }}" class="block text-sm font-medium text-[#374151]">{{ $label }}</label>
        {{ $link ?? '' }}
    </div>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-[#6B7280]">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>
        </span>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($toggle) type="password" :type="show ? 'text' : 'password'" @else type="{{ $type }}" @endif
            @if ($strength) x-model="pw" @endif
            @if ($type !== 'password' && ! $toggle) value="{{ old($name) }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($minlength) minlength="{{ $minlength }}" @endif
            placeholder="{{ $placeholder }}"
            required
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            class="w-full rounded-2xl border {{ $hasError ? 'border-red-400' : 'border-transparent' }} bg-[#F3F4F6] py-3 pl-11 {{ $toggle ? 'pr-11' : 'pr-4' }} text-sm text-[#0A0F1F] placeholder:text-[#6B7280]/70 transition focus:border-[#F59E0B] focus:bg-white focus:outline-none"
        />
        @if ($toggle)
            <button type="button" @click="show = !show" :aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-[#6B7280] hover:text-[#0A0F1F]">
                <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.3 21.3 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a21.3 21.3 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" /><line x1="1" y1="1" x2="23" y2="23" /></svg>
            </button>
        @endif
    </div>
    @if ($strength)
        <div x-show="pw.length" x-cloak class="mt-2 flex items-center gap-3">
            <div class="h-1 flex-1 overflow-hidden rounded-full bg-[#E5E7EB]">
                <div class="h-full rounded-full transition-all duration-300" :style="{ width: meter.width, background: meter.color }"></div>
            </div>
            <span class="w-12 text-right text-xs font-medium" :style="{ color: meter.color }" x-text="meter.label" aria-live="polite"></span>
        </div>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
