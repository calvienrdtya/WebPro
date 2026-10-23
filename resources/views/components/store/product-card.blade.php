@props(['product', 'layout' => 'grid'])

@php
    /** @var \App\Models\Product $product */
    $offer = $product->saleOptions()->first();
    $url = route('products.show', $product->slug);
    $stock = (int) $product->stock_count;
    $unit = $offer['months'] === 1 ? '/bulan' : '/'.$offer['months'].' bulan';
    $points = array_slice($product->highlights ?? [], 0, 3);
    $discountLabel = 'Diskon '.$offer['discount'].'%';
    $waNumber = \App\Support\Whatsapp::number();
    $waUrl = \App\Support\Whatsapp::url(\App\Support\Whatsapp::productMessage($product));
@endphp

@if ($layout === 'featured')
    <article class="group flex h-full gap-3 rounded-[20px] border border-[#E5E7EB] bg-white p-2.5 shadow-[0_4px_20px_rgba(0,0,0,0.04)] transition duration-200 hover:border-[#F59E0B]/50 hover:shadow-[0_8px_28px_rgba(0,0,0,0.08)] sm:p-3">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="relative h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-[#F3F4F6] sm:h-[112px] sm:w-[112px]">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="" width="112" height="112" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
            @endif
        </a>

        <div class="flex min-w-0 flex-1 flex-col">
            <h3 class="line-clamp-2 text-[15px] font-semibold leading-snug tracking-tight text-[#0A0F1F] sm:text-base">
                <a href="{{ $url }}" class="hover:underline">{{ $product->name }}</a>
            </h3>

            <div class="mt-1.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <p class="whitespace-nowrap text-lg font-bold leading-none tracking-tight text-[#0A0F1F] [font-feature-settings:'tnum']">@rupiah($offer['final_price'])</p>
                @if ($offer['discount'] > 0)
                    <p class="text-xs text-[#6B7280]">dari <span class="line-through">@rupiah($offer['list_price'])</span> {{ $unit }}</p>
                @else
                    <p class="text-xs text-[#6B7280]">{{ $unit }}</p>
                @endif
            </div>

            <div class="mt-auto flex items-stretch gap-2 pt-3">
                @if ($offer['discount'] > 0)
                    <span class="flex shrink-0 items-center justify-center gap-1 whitespace-nowrap rounded-2xl border border-[#10B981] bg-white px-2 py-1 text-xs font-bold tracking-[-0.01em] text-[#047857] motion-safe:animate-badge-blink" aria-label="{{ $discountLabel }}"><span>Diskon</span><span class="leading-none">{{ $offer['discount'] }}%</span></span>
                @endif
                <a href="{{ $url }}" class="btn-shine flex min-w-0 flex-1 items-center justify-center gap-1 whitespace-nowrap rounded-2xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-2 py-1 text-xs font-semibold text-[#0A0F1F] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:brightness-105">Beli</a>
            </div>
        </div>
    </article>
@else
    <article class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white p-2 shadow-[0_8px_30px_rgba(15,23,42,0.06)] transition duration-300 hover:shadow-[0_20px_45px_rgba(15,23,42,0.13)] sm:rounded-[2rem] sm:p-3.5">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true" class="relative block aspect-video overflow-hidden rounded-xl bg-slate-100 sm:rounded-[1.35rem]">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="" width="400" height="225" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
            @endif
        </a>

        <div class="mt-2 flex flex-wrap items-center gap-1 sm:mt-4 sm:gap-1.5">
            @if ($stock === 0)
                <span class="inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-bold text-slate-600 ring-1 ring-inset ring-slate-200 sm:px-2.5 sm:py-1 sm:text-xs">Stok habis</span>
            @elseif ($stock <= 5)
                <span class="inline-flex items-center rounded-full bg-rose-50 px-1.5 py-0.5 text-xs font-bold text-rose-700 ring-1 ring-inset ring-rose-200 motion-safe:animate-blink-slow sm:px-2.5 sm:py-1 sm:text-xs">Sisa stok {{ $stock }}</span>
            @else
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-1.5 py-0.5 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200 sm:px-2.5 sm:py-1 sm:text-xs">Stok tersedia</span>
            @endif
            @if ($offer['discount'] > 0)
                <span class="inline-flex items-center gap-1 rounded-full border border-[#10B981] bg-white px-1.5 py-0.5 text-xs font-bold text-[#047857] motion-safe:animate-badge-blink sm:px-2.5 sm:py-1" aria-label="{{ $discountLabel }}"><span>Diskon</span><span class="leading-none">{{ $offer['discount'] }}%</span></span>
            @endif
        </div>

        <h3 class="mt-2 line-clamp-2 text-[13px] font-semibold leading-[1.3] tracking-tight text-slate-900 sm:mt-3 sm:text-lg sm:font-bold sm:leading-tight">
            <a href="{{ $url }}" class="hover:underline">{{ $product->name }}</a>
        </h3>

        <div class="mt-1.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5 sm:mt-2">
            <p class="whitespace-nowrap text-base font-bold leading-none tracking-tight text-slate-900 [font-feature-settings:'tnum'] sm:text-xl sm:font-extrabold">@rupiah($offer['final_price'])</p>
            @if ($offer['discount'] > 0)
                <p class="text-xs text-[#6B7280] sm:text-xs">dari <span class="line-through">@rupiah($offer['list_price'])</span> {{ $unit }}</p>
            @else
                <p class="text-xs text-[#6B7280] sm:text-xs">{{ $unit }}</p>
            @endif
        </div>

        <div class="mt-auto pt-2 sm:pt-3">
            @if ($points)
                <ul class="space-y-1 text-xs leading-[1.4] text-slate-600 sm:space-y-2 sm:text-xs">
                    @foreach ($points as $point)
                        <li class="flex items-center gap-1.5">
                            <svg class="h-3 w-3 shrink-0 text-[#F59E0B] sm:h-3.5 sm:w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 10.7a1 1 0 1 1 1.4-1.4l3.1 3.1 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            <span class="line-clamp-1">{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-3 flex items-stretch gap-2 sm:mt-4 sm:gap-3">
                @if ($waNumber)
                    <a href="{{ $waUrl }}" target="_blank" rel="noopener" aria-label="Tanya {{ $product->name }} via WhatsApp" class="flex shrink-0 items-center justify-center rounded-2xl border border-[#25D366] bg-white px-2.5 text-lg text-[#25D366] transition hover:bg-[#25D366]/10 sm:px-3.5 sm:text-xl">
                        <x-store.brand-icon name="whatsapp" />
                    </a>
                @endif
                <a href="{{ $url }}" class="btn-shine flex min-w-0 flex-1 items-center justify-center gap-1 whitespace-nowrap rounded-2xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-2 py-1 text-xs font-semibold text-[#0A0F1F] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:brightness-105 sm:gap-1.5 sm:px-4 sm:py-1.5 sm:text-sm">Beli</a>
            </div>
        </div>
    </article>
@endif
