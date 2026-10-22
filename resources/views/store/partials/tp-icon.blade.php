{{-- Ikon halaman Top Pelanggan (dari frontend/top-pelanggan.html). Pakai: @include('store.partials.tp-icon', ['name' => 'trophy']) --}}
@switch ($name)
    @case ('trophy')
<span class="trophy reveal" style="--d:.05s" aria-hidden="true">
            <svg width="132" height="150" viewBox="0 0 120 140" fill="none">
              <path d="M32 30C6 28 6 66 36 70" stroke="url(#gGoldDark)" stroke-width="7" stroke-linecap="round"/>
              <path d="M88 30c26-2 26 36-4 40" stroke="url(#gGoldDark)" stroke-width="7" stroke-linecap="round"/>
              <path d="M28 14h64v34a32 32 0 0 1-64 0V14Z" fill="url(#gGold)"/>
              <path d="M28 14h64v8H28z" fill="#fff" fill-opacity=".28"/>
              <path d="M38 24v24a22 22 0 0 0 10 18" stroke="#fff" stroke-opacity=".5" stroke-width="4" stroke-linecap="round"/>
              <path d="m60 30 4.6 9.6 10.4 1.4-7.6 7.3 1.9 10.4L60 53.6l-9.3 4.9 1.9-10.4-7.6-7.3 10.4-1.4L60 30Z" fill="#92400E" fill-opacity=".75"/>
              <rect x="53" y="80" width="14" height="22" fill="url(#gGoldDark)"/>
              <rect x="36" y="100" width="48" height="12" rx="3" fill="url(#gGold)"/>
              <rect x="28" y="112" width="64" height="16" rx="4" fill="url(#gGoldDark)"/>
              <rect x="28" y="112" width="64" height="4" rx="2" fill="#fff" fill-opacity=".3"/>
            </svg>
          </span>
        @break
    @case ('stat0')
<span class="ico-cyan"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></svg></span>
        @break
    @case ('stat1')
<span class="ico-blue"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span>
        @break
    @case ('stat2')
<span class="ico-purple"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span>
        @break
    @case ('seg-week')
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M7 15h2M11 15h2M15 15h2"/></svg>
        @break
    @case ('seg-month')
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><circle cx="12" cy="15.5" r="1.4" fill="currentColor"/></svg>
        @break
    @case ('seg-all')
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.2 8.2c-2.6 0-4.4 2-6.2 3.8s-3.6 3.8-6.2 3.8a3.8 3.8 0 1 1 0-7.6c2.6 0 4.4 2 6.2 3.8s3.6 3.8 6.2 3.8a3.8 3.8 0 1 0 0-7.6Z"/></svg>
        @break
@endswitch
