<section>
    <h2 class="text-lg font-bold text-[#0A0F1F]">Notifikasi</h2>
    <p class="mt-2 text-sm text-[#6B7280]">Email akun dan status pesanan dikirim ke <span class="font-medium text-[#0A0F1F]">email yang kamu isi saat checkout</span>. Pengaturan notifikasi WhatsApp dan promo menyusul.</p>
</section>

<div class="my-8 border-t border-slate-100"></div>

<section id="hapus" x-data="{ open: {{ old('_form') === 'hapus' ? 'true' : 'false' }} }">
    <h2 class="text-lg font-bold text-[#0A0F1F]">Hapus Akun</h2>
    <div class="mt-4 rounded-2xl border-2 border-rose-100 bg-rose-50 p-5">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
            <div>
                <p class="text-sm font-semibold text-rose-700">Tindakan ini tidak dapat dibatalkan</p>
                <p class="mt-1 text-sm text-rose-700">Data pribadi Anda (nama, email, WhatsApp, voucher) dihapus dan Anda keluar dari akun. Riwayat pesanan tetap tersimpan secara anonim untuk pencatatan toko.</p>
            </div>
        </div>
        @if ($canDelete)
            <button type="button" x-show="!open" @click="open = true" class="mt-4 rounded-2xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 active:scale-[0.99]">Hapus Akun Saya</button>
            <form x-show="open" x-cloak method="POST" action="{{ route('profil.destroy') }}" class="mt-4 space-y-3" novalidate>
                @csrf
                @method('DELETE')
                <input type="hidden" name="_form" value="hapus">
                <div>
                    <label for="del-confirm" class="mb-1.5 block text-[13px] font-medium text-rose-800">Ketik <strong>HAPUS</strong> untuk mengonfirmasi</label>
                    <input id="del-confirm" name="confirm" type="text" autocomplete="off" class="pf-input !pl-4" />
                    @if (old('_form') === 'hapus') @error('confirm') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror @endif
                </div>
                @if ($hasPassword)
                    <div>
                        <label for="del-password" class="mb-1.5 block text-[13px] font-medium text-rose-800">Kata sandi</label>
                        <input id="del-password" name="password" type="password" autocomplete="current-password" class="pf-input !pl-4" />
                        @error('password') <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-2xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 active:scale-[0.99]">Ya, hapus akun saya</button>
                    <button type="button" @click="open = false" class="rounded-2xl border border-rose-200 bg-white px-5 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">Batal</button>
                </div>
            </form>
        @else
            <p class="mt-4 text-sm font-medium text-rose-700">Akun admin tidak bisa dihapus dari halaman ini.</p>
        @endif
    </div>
</section>
