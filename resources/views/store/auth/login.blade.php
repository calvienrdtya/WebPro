@extends('layouts.store')

@section('title', 'Login — '.\App\Support\StoreProfile::name())
@section('description', 'Masuk ke akun ProAkses untuk mengelola pesanan akun premium Anda.')

@push('meta')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <x-store.auth-card>
        <x-slot:brand>
            <div class="relative">
                <h2 class="text-3xl font-bold leading-tight">Halo lagi! 👋<br />senang lihat kamu balik.</h2>
                <p class="mt-4 text-sm text-[#FEF3C7]/80">
                    Masuk untuk memantau status pesanan, akun aktif, dan riwayat transaksi kamu semua di satu tempat.
                </p>
            </div>

            <ul class="relative space-y-4 text-base text-[#FEF3C7]">
                <x-store.perk>
                    <x-slot:icon><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z" /></x-slot:icon>
                    Kirim Instan
                </x-store.perk>
                <x-store.perk>
                    <x-slot:icon><path d="M12 3 5 6v5c0 5 3 8.5 7 10 4-1.5 7-5 7-10V6l-7-3Z" /><path d="m9 12 2 2 4-4" /></x-slot:icon>
                    Transaksi Aman dan Terpercaya
                </x-store.perk>
                <x-store.perk>
                    <x-slot:icon><path d="M4 13a8 8 0 0 1 16 0" /><path d="M4 13v4a2 2 0 0 0 2 2h1v-6H5a1 1 0 0 0-1 1Z" /><path d="M20 13v4a2 2 0 0 1-2 2h-1v-6h1a1 1 0 0 1 1 1Z" /><path d="M15 19a3 3 0 0 1-3 2" /></x-slot:icon>
                    Garansi &amp; Support Responsif
                </x-store.perk>
            </ul>
        </x-slot:brand>

        <h1 class="text-2xl font-bold tracking-tight text-[#0A0F1F]">Masuk ke akun kamu</h1>
        <p class="mt-2 text-sm text-[#6B7280]">
            Belum punya akun?
            <a href="{{ route('register') }}" class="font-semibold text-[#B45309] transition hover:opacity-80">Daftar di sini</a>
        </p>

        @error('google')
            <p role="alert" class="mt-6 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p>
        @enderror

        <x-store.google-button>Lanjutkan dengan Google</x-store.google-button>

        <div class="mt-6 flex items-center gap-3">
            <div class="h-px flex-1 bg-[#D1D5DB]"></div>
            <span class="text-xs text-[#6B7280]">atau masuk dengan email</span>
            <div class="h-px flex-1 bg-[#D1D5DB]"></div>
        </div>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <x-store.field id="email" name="email" label="Email" type="email" icon="mail" placeholder="nama@email.com" autocomplete="email" />

            <x-store.field id="password" name="password" label="Kata sandi" icon="lock" placeholder="••••••••" autocomplete="current-password" toggle>
                <x-slot:link>
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-[#B45309] transition hover:opacity-80">Lupa kata sandi?</a>
                </x-slot:link>
            </x-store.field>

            <label class="flex items-center gap-2 text-sm text-[#374151]">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-[#D1D5DB] text-[#F59E0B] focus:ring-[#F59E0B]" />
                Ingat saya
            </label>

            <button type="submit"
                class="btn-shine w-full rounded-2xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-4 py-3 text-sm font-semibold text-[#0A0F1F] shadow-lg shadow-[#F59E0B]/30 transition hover:brightness-105 active:scale-[0.99]">
                Masuk
            </button>
        </form>
    </x-store.auth-card>
@endsection
