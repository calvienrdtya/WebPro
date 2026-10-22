<main id="main" class="relative z-10 flex min-h-[calc(100vh-4rem)] items-center justify-center px-5 pb-16 pt-4 sm:px-8 sm:pb-20 sm:pt-6">
    <div class="grid w-full max-w-4xl overflow-hidden rounded-[2rem] bg-white shadow-xl shadow-slate-300/40 ring-1 ring-[#D1D5DB]/50 lg:grid-cols-2">
        <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-[#0A0F1F] via-[#131A33] to-[#1A1B3A] p-10 text-white lg:flex">
            <div class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-[#F59E0B]/15 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-[#FBBF24]/10 blur-2xl"></div>
            {{ $brand }}
        </div>

        <div class="p-8 sm:p-10">
            {{ $slot }}
        </div>
    </div>
</main>
