@extends('layouts.store')

@section('title', 'Daftar — '.\App\Support\StoreProfile::name())
@section('description', 'Buat akun ProAkses gratis untuk membeli akun premium dengan harga rupiah.')

@push('meta')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <x-store.auth-card>
        <x-slot:brand>
            <div class="relative">
                <h2 class="text-3xl font-bold leading-tight">Gabung sekarang,<br />mulai belanja hemat.</h2>
                <p class="mt-4 text-sm text-[#FEF3C7]/80">
                    Buat akun gratis untuk akses akun AI premium ChatGPT Plus, Claude Pro, Gemini Advanced, dan lainnya — harga rupiah.
                </p>
            </div>

            <ul class="relative space-y-4 text-base text-[#FEF3C7]">
                <x-store.perk>
                    <x-slot:icon><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M19 8v6M22 11h-6" /></x-slot:icon>
                    Daftar Gratis dalam 1 Menit
                </x-store.perk>
                <x-store.perk>
                    <x-slot:icon><rect x="3" y="9" width="18" height="12" rx="2" /><path d="M3 9V7a2 2 0 0 1 2-2h2.5a2.5 2.5 0 0 1 0 5H12M21 9V7a2 2 0 0 0-2-2h-2.5a2.5 2.5 0 0 0 0 5H12M12 4v17" /></x-slot:icon>
                    Promo Spesial Member Baru
                </x-store.perk>
                <x-store.perk>
                    <x-slot:icon><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3.5 2" /></x-slot:icon>
                    Pantau Pesanan Real-time
                </x-store.perk>
            </ul>
        </x-slot:brand>

        <h1 class="text-2xl font-bold tracking-tight text-[#0A0F1F]">Buat akun baru</h1>
        <p class="mt-2 text-sm text-[#6B7280]">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-semibold text-[#B45309] transition hover:opacity-80">Masuk di sini</a>
        </p>

        <x-store.google-button>Daftar dengan Google</x-store.google-button>

        <div class="mt-6 flex items-center gap-3">
            <div class="h-px flex-1 bg-[#D1D5DB]"></div>
            <span class="text-xs text-[#6B7280]">atau daftar dengan email</span>
            <div class="h-px flex-1 bg-[#D1D5DB]"></div>
        </div>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf
            <x-store.field id="name" name="name" label="Nama lengkap" icon="user" placeholder="Nama kamu" autocomplete="name" />
            <x-store.field id="email" name="email" label="Email" type="email" icon="mail" placeholder="nama@email.com" autocomplete="email" />
            <x-store.field id="password" name="password" label="Kata sandi" icon="lock" placeholder="Minimal 8 karakter, huruf dan angka" autocomplete="new-password" minlength="8" toggle strength />

            <div>
                <label class="flex items-start gap-2 text-sm text-[#374151]">
                    <input type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-0.5 h-4 w-4 rounded border-[#D1D5DB] text-[#F59E0B] focus:ring-[#F59E0B]" />
                    <span>
                        Saya menyetujui
                        <a href="{{ route('legal.show', 'syarat-ketentuan') }}" target="_blank" rel="noopener" class="font-medium text-[#B45309] transition hover:opacity-80">Syarat &amp; Ketentuan</a>
                        dan
                        <a href="{{ route('legal.show', 'kebijakan-privasi') }}" target="_blank" rel="noopener" class="font-medium text-[#B45309] transition hover:opacity-80">Kebijakan Privasi</a>
                        ProAkses.
                    </span>
                </label>
                @error('terms')
                    <p class="mt-1.5 text-xs text-red-600">Kamu harus menyetujui Syarat &amp; Ketentuan dan Kebijakan Privasi.</p>
                @enderror
            </div>

            <button type="submit"
                class="btn-shine w-full rounded-2xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-4 py-3 text-sm font-semibold text-[#0A0F1F] shadow-lg shadow-[#F59E0B]/30 transition hover:brightness-105 active:scale-[0.99]">
                Daftar
            </button>
        </form>
    </x-store.auth-card>
@endsection
