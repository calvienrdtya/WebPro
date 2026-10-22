{{-- Paginasi gaya profil (po-pg). Dipakai paginator ->links('store.partials.pagination'). --}}
@if ($paginator->hasPages())
    <nav aria-label="Halaman" class="flex items-center gap-1.5">
        @if ($paginator->onFirstPage())
            <span class="po-pg po-pg--nav opacity-40" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="po-pg po-pg--nav" aria-label="Halaman sebelumnya"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-sm text-[#9CA3AF]">{{ $element }}</span>
            @elseif (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="po-pg po-pg--active" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="po-pg" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="po-pg po-pg--nav" aria-label="Halaman berikutnya"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a>
        @else
            <span class="po-pg po-pg--nav opacity-40" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
        @endif
    </nav>
@endif
