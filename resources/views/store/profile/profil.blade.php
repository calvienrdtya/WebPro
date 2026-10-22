<div class="lg:col-span-3">
    <section class="pf-card p-5 sm:p-7" style="--d:0s">
        <div class="flex items-center gap-3">
            <span class="pf-ic h-9 w-9" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <h2 class="text-xl font-bold tracking-tight text-[#0A0F1F] sm:text-[22px]">Informasi Pribadi</h2>
        </div>
        <p class="mt-3 text-sm text-[#6B7280] sm:text-[15px]">Perbarui data pribadi Anda agar pesanan dan notifikasi tetap akurat.</p>

        <form method="POST" action="{{ route('profil.update') }}" class="mt-6 space-y-5" novalidate>
            @csrf
            @method('PATCH')
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="full-name" class="mb-2 block text-sm font-medium text-[#374151]">Nama Lengkap</label>
                    <input id="full-name" name="name" type="text" required maxlength="100" value="{{ old('_form') === 'profil' ? old('name') : $user->name }}" class="pf-input !pl-4" autocomplete="name" />
                    @if (old('_form') === 'profil') @error('name') <p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror @endif
                </div>
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-[#374151]">Email</label>
                    <input id="email" type="email" value="{{ $user->email }}" class="pf-input !pl-4 cursor-not-allowed bg-[#F3F4F6] text-[#6B7280]" readonly aria-describedby="email-note" />
                    <p id="email-note" class="mt-1.5 text-xs text-[#6B7280]">Email dipakai untuk masuk dan tidak bisa diubah.</p>
                </div>
                <div class="sm:col-span-2 sm:max-w-[calc(50%-0.625rem)]">
                    <label for="whatsapp" class="mb-2 block text-sm font-medium text-[#374151]">WhatsApp</label>
                    <input id="whatsapp" name="phone" type="tel" inputmode="tel" maxlength="20" value="{{ old('_form') === 'profil' ? old('phone') : $user->phone }}" placeholder="08xxxxxxxxxx" class="pf-input !pl-4" autocomplete="tel" />
                    @if (old('_form') === 'profil') @error('phone') <p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror @endif
                </div>
            </div>
            <input type="hidden" name="_form" value="profil">

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="submit" class="submit-btn pf-save"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>Simpan Perubahan</button>
                <a href="{{ route('profil', ['tab' => 'profil']) }}" class="pf-cancel">Batal</a>
            </div>
        </form>
    </section>
</div>

<div class="lg:col-span-2">
    <div class="space-y-5 lg:sticky lg:top-40">
        <div class="pf-card p-5 sm:p-6" style="--d:.08s">
            <div class="flex items-center gap-3">
                <span class="pf-ic h-8 w-8" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg></span>
                <h3 class="text-base font-semibold text-[#0A0F1F]">Statistik Akun</h3>
            </div>
            <dl class="mt-3">
                <div class="flex items-center justify-between border-b border-[#F3F4F6] py-2.5"><dt class="text-sm text-[#6B7280]">Total Pesanan</dt><dd class="text-[15px] font-semibold text-[#0A0F1F]">{{ number_format($stats['orders'], 0, ',', '.') }}</dd></div>
                <div class="flex items-center justify-between border-b border-[#F3F4F6] py-2.5"><dt class="text-sm text-[#6B7280]">Total Transaksi</dt><dd class="text-[15px] font-semibold text-[#0A0F1F]">Rp{{ number_format($stats['spent'], 0, ',', '.') }}</dd></div>
                <div class="flex items-center justify-between border-b border-[#F3F4F6] py-2.5"><dt class="text-sm text-[#6B7280]">Member Sejak</dt><dd class="text-[15px] font-semibold text-[#0A0F1F]">{{ $user->created_at->translatedFormat('F Y') }}</dd></div>
                <div class="flex items-center justify-between pb-0.5 pt-2.5"><dt class="text-sm text-[#6B7280]">Status</dt><dd><span class="inline-flex items-center gap-1 rounded-full border border-[#10B981]/30 bg-[#10B981]/15 px-3 py-1 text-xs font-semibold text-[#047857]">Aktif</span></dd></div>
            </dl>
        </div>

        <div class="pf-card p-5 sm:p-6" style="--d:.16s" x-data="{ open: {{ old('_form') === 'password' ? 'true' : 'false' }} }">
            <div class="flex items-center gap-3">
                <span class="pf-ic h-8 w-8" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg></span>
                <h3 class="text-base font-semibold text-[#0A0F1F]">Keamanan</h3>
            </div>
            <div class="mt-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-[#0A0F1F]">Kata sandi</p>
                    <p class="mt-0.5 text-[12.5px] text-[#6B7280]">{{ $hasPassword ? 'Ganti kata sandi akun Anda.' : 'Akun ini masuk lewat Google. Buat kata sandi untuk masuk dengan email.' }}</p>
                </div>
                <button type="button" class="pf-change" @click="open = !open" :aria-expanded="open">{{ $hasPassword ? 'Ganti' : 'Buat' }}</button>
            </div>
            <form x-show="open" x-cloak method="POST" action="{{ route('profil.password') }}" class="mt-4 space-y-3" novalidate>
                @csrf
                @method('PATCH')
                <input type="hidden" name="_form" value="password">
                @if ($hasPassword)
                    <div>
                        <label for="pw-current" class="mb-1.5 block text-[13px] font-medium text-[#374151]">Kata sandi saat ini</label>
                        <input id="pw-current" name="current_password" type="password" autocomplete="current-password" class="pf-input !pl-4" />
                        @if (old('_form') === 'password') @error('current_password') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror @endif
                    </div>
                @endif
                <div>
                    <label for="pw-new" class="mb-1.5 block text-[13px] font-medium text-[#374151]">Kata sandi baru</label>
                    <input id="pw-new" name="password" type="password" autocomplete="new-password" class="pf-input !pl-4" />
                    @if (old('_form') === 'password') @error('password') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror @endif
                    <p class="mt-1 text-xs text-[#6B7280]">Minimal 8 karakter, berisi huruf dan angka.</p>
                </div>
                <div>
                    <label for="pw-confirm" class="mb-1.5 block text-[13px] font-medium text-[#374151]">Ulangi kata sandi baru</label>
                    <input id="pw-confirm" name="password_confirmation" type="password" autocomplete="new-password" class="pf-input !pl-4" />
                </div>
                <button type="submit" class="pf-save w-full !min-h-[42px]">Simpan Kata Sandi</button>
            </form>
        </div>

        <div class="pf-help p-5 sm:p-6" style="--d:.24s">
            <h3 class="text-base font-semibold text-white">Butuh Bantuan?</h3>
            <div class="mt-3 space-y-1">
                <a href="{{ $waUrl }}" rel="noopener">
                    <span class="pf-help-ic bg-[#25D366]/15 text-[#25D366]"><x-store.brand-icon name="whatsapp" /></span>
                    Chat CS via WhatsApp
                </a>
                <a href="{{ route('faq') }}">
                    <span class="pf-help-ic bg-[#FBBF24]/15 text-[#FBBF24]"><x-store.brand-icon name="circle-question" /></span>
                    Lihat FAQ
                </a>
            </div>
        </div>
    </div>
</div>
