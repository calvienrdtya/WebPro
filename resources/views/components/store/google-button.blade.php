<a href="{{ route('auth.google') }}"
    class="mt-6 flex w-full items-center justify-center gap-2 rounded-2xl border border-[#D1D5DB] bg-white px-4 py-3 text-sm font-semibold text-[#374151] transition hover:bg-[#F3F4F6]">
    <svg width="18" height="18" viewBox="0 0 24 24" class="shrink-0" aria-hidden="true">
        <path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.66-.22-2.45H12v4.64h6.47c-.28 1.5-1.13 2.77-2.4 3.62v3h3.88c2.27-2.09 3.57-5.17 3.57-8.81Z" />
        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.94-2.92l-3.88-3c-1.08.72-2.45 1.15-4.06 1.15-3.12 0-5.77-2.11-6.71-4.94H1.28v3.1A11.998 11.998 0 0 0 12 24Z" />
        <path fill="#FBBC05" d="M5.29 14.29a7.2 7.2 0 0 1 0-4.58v-3.1H1.28a12 12 0 0 0 0 10.78l4.01-3.1Z" />
        <path fill="#EA4335" d="M12 4.75c1.76 0 3.35.6 4.6 1.79l3.44-3.44C17.94 1.19 15.24 0 12 0 7.31 0 3.26 2.69 1.28 6.61l4.01 3.1C6.23 6.87 8.88 4.75 12 4.75Z" />
    </svg>
    {{ $slot }}
</a>
