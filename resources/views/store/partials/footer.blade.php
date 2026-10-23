@php
    $store = \App\Support\StoreProfile::class;
    $socials = $store::activeSocials();
    $wa = \App\Support\Whatsapp::url();
    $footerProducts = \App\Models\Product::active()->orderBy('sort_order')->orderBy('name')->limit(3)->get(['name', 'slug']);
@endphp

<footer class="border-t border-slate-100 bg-white">
    <div class="mx-auto max-w-6xl px-5 pb-0 pt-16 sm:px-8">
        <div class="grid grid-cols-2 gap-10 md:grid-cols-4">
            <div class="col-span-2 md:col-span-1">
                <p class="text-lg font-bold text-slate-900">{{ $store::name() }}</p>
                @if ($store::text('tagline') !== '')
                    <p class="mt-3 max-w-xs text-sm text-slate-600">{{ $store::text('tagline') }}</p>
                @endif
                <div class="mt-5 flex items-center gap-3">
                    @foreach ($socials as $social)
                        <a href="{{ $social['url'] }}" aria-label="{{ $social['label'] }}" target="_blank" rel="noopener" style="--brand: {{ $social['color'] }}" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-lg text-[var(--brand)] transition hover:-translate-y-0.5 hover:bg-[var(--brand)] hover:text-white">
                            <x-store.brand-icon :name="$social['icon']" />
                        </a>
                    @endforeach
                </div>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900">Produk</p>
                <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                    @foreach ($footerProducts as $p)
                        <li><a href="{{ route('products.show', $p->slug) }}" class="transition hover:text-[#B45309]">{{ $p->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('products.index') }}" class="transition hover:text-[#B45309]">Semua produk</a></li>
                </ul>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900">Bantuan</p>
                <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                    <li><a href="{{ route('home') }}#cara-order" class="transition hover:text-[#B45309]">Cara order</a></li>
                    <li><a href="{{ route('faq') }}" class="transition hover:text-[#B45309]">FAQ</a></li>
                    <li><a href="{{ route('reports.create') }}" class="transition hover:text-[#B45309]">Lapor masalah</a></li>
                    @foreach (\App\Support\LegalPages::keys() as $legal)
                        @php($legalMeta = \App\Support\LegalPages::meta($legal))
                        <li><a href="{{ $legalMeta['url'] }}" class="transition hover:text-[#B45309]">{{ $legalMeta['title'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900">Kontak</p>
                <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                    <li><a href="{{ $wa }}" rel="noopener" class="transition hover:text-[#B45309]">WhatsApp admin</a></li>
                    @foreach (array_filter($socials, fn ($s) => ! $s['managed']) as $social)
                        <li><a href="{{ $social['url'] }}" target="_blank" rel="noopener" class="transition hover:text-[#B45309]">{{ $social['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="mt-12 flex flex-row items-center justify-between gap-4 border-t border-slate-200 pb-6 pt-6 text-left">
            <p class="text-sm text-slate-500">&copy; {{ date('Y') }} {{ $store::name() }}. Seluruh hak cipta dilindungi.</p>
            @if ($store::text('footer_note') !== '')
                <p class="text-xs text-slate-500">{{ $store::text('footer_note') }}</p>
            @endif
        </div>
    </div>
</footer>
