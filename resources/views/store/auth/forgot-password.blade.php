@extends('layouts.store')

@section('title', 'Lupa Sandi — '.\App\Support\StoreProfile::name())
@section('description', 'Reset password akun ProAkses kamu lewat kode verifikasi yang dikirim ke email.')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
  <main id="main" class="relative z-10 flex min-h-[calc(100vh-4rem)] items-center justify-center px-4 pb-16 pt-4 sm:px-8 sm:pb-20 sm:pt-6">
    <div id="reset-card" data-send-url="{{ route('password.send') }}" data-verify-url="{{ route('password.verify') }}" data-update-url="{{ route('password.update') }}" class="grid w-full max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-xl shadow-slate-300/40 ring-1 ring-[#D1D5DB]/50 lg:grid-cols-[2fr_3fr]">

      <!-- Kiri: panel brand (40%) -->
      <aside class="relative flex flex-col justify-between gap-6 overflow-hidden bg-gradient-to-br from-[#0A0F1F] via-[#131A33] to-[#1A1B3A] p-6 text-white sm:p-8 lg:gap-10 lg:p-10">
        <div class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-[#F59E0B]/15 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-[#FBBF24]/10 blur-2xl"></div>

        <div class="relative">
          <img src="{{ \App\Support\StoreProfile::imageUrl('logo') }}" alt="{{ \App\Support\StoreProfile::name() }}" {!! \App\Support\StoreProfile::imageSize('logo') !!} class="-ml-3 mb-4 h-14 w-auto max-w-[220px] object-contain lg:mb-8 lg:h-16" />
          <h2 class="text-2xl font-bold leading-tight text-white lg:text-[32px]">Lupa Sandi?</h2>
          <p class="mt-3 text-sm text-[#FEF3C7]/80 lg:text-[15px]">
            Tenang, kami akan bantu kamu reset password dengan cepat dan aman.
          </p>
        </div>

        <ul class="relative hidden space-y-4 text-[15px] text-[#FEF3C7] lg:block">
          <li class="flex items-center gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#FBBF24] ring-1 ring-[#FBBF24]/30"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span>
            Kode verifikasi dikirim ke email
          </li>
          <li class="flex items-center gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#FBBF24] ring-1 ring-[#FBBF24]/30"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span>
            Kode berlaku 5 menit
          </li>
          <li class="flex items-center gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#FBBF24] ring-1 ring-[#FBBF24]/30"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span>
            Reset password aman &amp; cepat
          </li>
        </ul>
        <a href="{{ route('login') }}" class="relative text-sm font-semibold text-[#FBBF24] transition hover:opacity-80">Kembali ke Login <span aria-hidden="true">←</span></a>
      </aside>

      <!-- Kanan: form (60%) -->
      <section class="flex items-center p-6 sm:p-10 lg:p-12">
        <div class="mx-auto w-full max-w-[460px]">

          <!-- Form flow -->
          <div id="flow">
            <header class="mb-6 sm:mb-8">
              <h1 id="title" class="text-2xl font-bold tracking-tight text-[#0A0F1F] sm:text-[28px]">Reset Password</h1>
              <p id="subtitle" class="mt-2 text-sm text-[#6B7280] sm:text-[15px]">Masukkan email yang terdaftar, kami akan kirim kode verifikasi 6 digit.</p>
            </header>

            <!-- STEP 1: email -->
            <form id="email-form" novalidate>
              <label for="email" class="mb-1.5 block text-[13px] font-medium text-[#374151] sm:text-sm">Email</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-[#6B7280]">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3" /><path d="m3 7 9 6 9-6" /></svg>
                </span>
                <input id="email" name="email" type="email" autocomplete="email" placeholder="email@contoh.com" aria-describedby="email-error" class="w-full rounded-xl border border-transparent bg-[#F3F4F6] py-3 pl-11 pr-4 text-[15px] text-[#0A0F1F] placeholder:text-[#6B7280]/70 transition focus:border-[#F59E0B] focus:bg-white focus:outline-none" />
              </div>
              <p id="email-error" role="alert" class="mt-1.5 hidden items-start gap-1.5 text-[13px] text-[#DC2626] anim-fade"><svg class="mt-px shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg><span></span></p>
              <div class="mt-5">
                <button type="submit" id="send-btn" class="btn-shine flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-4 py-3.5 text-[15px] font-semibold text-[#0A0F1F] shadow-[0_8px_20px_rgba(245,158,11,0.3)] transition hover:brightness-105 hover:shadow-[0_12px_26px_rgba(245,158,11,0.38)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100 disabled:active:scale-100" ><svg class="spinner hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg><span class="label" data-idle="Kirim Kode" data-loading="Mengirim...">Kirim Kode</span></button>
              </div>
            </form>

            <!-- STEP 2: kode -->
            <form id="code-form" hidden novalidate class="anim-slide-down mt-6 border-t border-[#E5E7EB] pt-6 sm:mt-8 sm:pt-8">
              <p class="text-[13px] text-[#6B7280] sm:text-sm">
                Kode 6 digit telah dikirim ke <strong id="sent-email" class="break-all font-semibold text-[#0A0F1F]"></strong>. Kode berlaku 5 menit.
              </p>
              <button type="button" id="change-email-btn" class="mt-1 text-[13px] font-semibold text-[#B45309] transition hover:opacity-80 sm:text-sm">Ganti email</button>
              <p id="timer" class="mt-2 text-[13px] text-[#B45309] sm:text-sm" aria-live="off">Kode berlaku dalam <span id="timer-value">05:00</span></p>
              <p id="expired" hidden class="mt-2 text-[13px] font-medium text-[#DC2626] sm:text-sm">Kode kadaluarsa. Kirim ulang.</p>

              <fieldset class="mt-5 min-w-0">
                <legend class="mb-2 block text-[13px] font-medium text-[#374151] sm:text-sm">Kode Verifikasi</legend>
                <div id="code-boxes" class="flex w-full items-center gap-1.5 sm:gap-3">
                  <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="Digit 1 dari 6" aria-describedby="code-error" class="code-box h-12 w-0 min-w-0 flex-1 rounded-xl border border-transparent bg-[#F3F4F6] text-center text-xl font-bold text-[#0A0F1F] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.15)] focus:outline-none sm:h-16 sm:text-[28px]" /><input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="Digit 2 dari 6" aria-describedby="code-error" class="code-box h-12 w-0 min-w-0 flex-1 rounded-xl border border-transparent bg-[#F3F4F6] text-center text-xl font-bold text-[#0A0F1F] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.15)] focus:outline-none sm:h-16 sm:text-[28px]" /><input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="Digit 3 dari 6" aria-describedby="code-error" class="code-box h-12 w-0 min-w-0 flex-1 rounded-xl border border-transparent bg-[#F3F4F6] text-center text-xl font-bold text-[#0A0F1F] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.15)] focus:outline-none sm:h-16 sm:text-[28px]" /><span aria-hidden="true" class="h-0.5 w-3 shrink-0 self-center rounded-full bg-[#9CA3AF] sm:w-4"></span><input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="Digit 4 dari 6" aria-describedby="code-error" class="code-box h-12 w-0 min-w-0 flex-1 rounded-xl border border-transparent bg-[#F3F4F6] text-center text-xl font-bold text-[#0A0F1F] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.15)] focus:outline-none sm:h-16 sm:text-[28px]" /><input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="Digit 5 dari 6" aria-describedby="code-error" class="code-box h-12 w-0 min-w-0 flex-1 rounded-xl border border-transparent bg-[#F3F4F6] text-center text-xl font-bold text-[#0A0F1F] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.15)] focus:outline-none sm:h-16 sm:text-[28px]" /><input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="Digit 6 dari 6" aria-describedby="code-error" class="code-box h-12 w-0 min-w-0 flex-1 rounded-xl border border-transparent bg-[#F3F4F6] text-center text-xl font-bold text-[#0A0F1F] transition focus:border-[#F59E0B] focus:bg-white focus:shadow-[0_0_0_3px_rgba(245,158,11,0.15)] focus:outline-none sm:h-16 sm:text-[28px]" />
                </div>
                <p id="code-error" role="alert" class="mt-1.5 hidden items-start gap-1.5 text-[13px] text-[#DC2626] anim-fade"><svg class="mt-px shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg><span></span></p>
              </fieldset>

              <div class="mt-5">
                <button type="submit" id="verify-btn" class="btn-shine flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-4 py-3.5 text-[15px] font-semibold text-[#0A0F1F] shadow-[0_8px_20px_rgba(245,158,11,0.3)] transition hover:brightness-105 hover:shadow-[0_12px_26px_rgba(245,158,11,0.38)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100 disabled:active:scale-100" disabled><svg class="spinner hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg><span class="label" data-idle="Verifikasi" data-loading="Memverifikasi...">Verifikasi</span></button>
              </div>

              <p class="mt-4 text-center text-[13px] text-[#6B7280] sm:text-sm">
                Tidak menerima kode?
                <button type="button" id="resend-btn" disabled class="font-semibold text-[#B45309] transition hover:opacity-80 disabled:cursor-not-allowed disabled:text-[#6B7280] disabled:hover:opacity-100">
                  <span id="resend-label">Kirim ulang dalam 00:30</span>
                </button>
              </p>
            </form>

            <!-- STEP 3: password baru -->
            <form id="password-form" hidden novalidate class="anim-slide-down">
              <p class="mb-5 flex items-center gap-2 text-sm font-medium text-[#047857] sm:text-[15px]">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#10B981]/15"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span>
                Kode terverifikasi! Sekarang buat password baru.
              </p>

              <div>
                <label for="password" class="mb-1.5 block text-[13px] font-medium text-[#374151] sm:text-sm">Password Baru</label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-[#6B7280]"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg></span>
                  <input id="password" type="password" autocomplete="new-password" placeholder="Minimal 8 karakter" aria-describedby="password-error password-hint" class="w-full rounded-xl border border-transparent bg-[#F3F4F6] py-3 pl-11 pr-4 text-[15px] text-[#0A0F1F] placeholder:text-[#6B7280]/70 transition focus:border-[#F59E0B] focus:bg-white focus:outline-none !pr-11" />
                  <button type="button" data-toggle="password" aria-label="Tampilkan password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-[#6B7280] hover:text-[#0A0F1F]"><svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" /><circle cx="12" cy="12" r="3" /></svg></button>
                </div>
                <div class="mt-2 hidden items-center gap-3" id="password-hint">
                  <div class="h-1 flex-1 overflow-hidden rounded-full bg-[#E5E7EB]"><div id="strength-bar" class="h-full w-0 rounded-full transition-all duration-300"></div></div>
                  <span id="strength-text" class="w-12 text-right text-xs font-medium text-[#6B7280]" aria-live="polite"></span>
                </div>
                <p id="password-error" role="alert" class="mt-1.5 hidden items-start gap-1.5 text-[13px] text-[#DC2626] anim-fade"><svg class="mt-px shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg><span></span></p>
              </div>

              <div class="mt-4">
                <label for="confirm" class="mb-1.5 block text-[13px] font-medium text-[#374151] sm:text-sm">Konfirmasi Password</label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-[#6B7280]"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg></span>
                  <input id="confirm" type="password" autocomplete="new-password" placeholder="Ulangi password baru" aria-describedby="confirm-error" class="w-full rounded-xl border border-transparent bg-[#F3F4F6] py-3 pl-11 pr-4 text-[15px] text-[#0A0F1F] placeholder:text-[#6B7280]/70 transition focus:border-[#F59E0B] focus:bg-white focus:outline-none !pr-11" />
                  <button type="button" data-toggle="confirm" aria-label="Tampilkan password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-[#6B7280] hover:text-[#0A0F1F]"><svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" /><circle cx="12" cy="12" r="3" /></svg></button>
                </div>
                <p id="confirm-error" role="alert" class="mt-1.5 hidden items-start gap-1.5 text-[13px] text-[#DC2626] anim-fade"><svg class="mt-px shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg><span></span></p>
              </div>

              <div class="mt-5">
                <button type="submit" id="save-btn" class="btn-shine flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-4 py-3.5 text-[15px] font-semibold text-[#0A0F1F] shadow-[0_8px_20px_rgba(245,158,11,0.3)] transition hover:brightness-105 hover:shadow-[0_12px_26px_rgba(245,158,11,0.38)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100 disabled:active:scale-100" disabled><svg class="spinner hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg><span class="label" data-idle="Simpan Password" data-loading="Menyimpan...">Simpan Password</span></button>
              </div>
            </form>
          </div>

          <!-- SUCCESS -->
          <div id="success" hidden class="anim-scale-in py-6 text-center" tabindex="-1">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-[#10B981]/15 text-[#047857]">
              <svg class="anim-pop" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
            </div>
            <h2 class="mt-6 text-[22px] font-bold text-[#0A0F1F] sm:text-2xl">Password Berhasil Direset!</h2>
            <p class="mt-2 text-sm text-[#6B7280] sm:text-[15px]">Sekarang kamu bisa login dengan password baru.</p>
            <a href="{{ route('login') }}" class="btn-shine flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-4 py-3.5 text-[15px] font-semibold text-[#0A0F1F] shadow-[0_8px_20px_rgba(245,158,11,0.3)] transition hover:brightness-105 hover:shadow-[0_12px_26px_rgba(245,158,11,0.38)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100 disabled:active:scale-100 mt-8">Kembali ke Login <span aria-hidden="true">→</span></a>
          </div>

        </div>
      </section>
    </div>
  </main>
@endsection

@push('scripts')
    @vite('resources/js/forgot-password.js')
@endpush
