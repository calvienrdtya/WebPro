@extends('layouts.store')

@section('title', $page['title'].' — '.\App\Support\StoreProfile::name())
@section('description', $page['description'])
@section('canonical', $page['url'])

@section('content')
    <main id="main" class="mx-auto max-w-4xl px-5 py-8 sm:px-8 md:py-12">
        <nav aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1.5 text-[13px] text-[#6B7280] sm:text-sm">
                <li><a href="{{ route('home') }}" class="transition hover:text-[#B45309]">Beranda</a></li>
                <li aria-hidden="true" class="text-[#D1D5DB]">/</li>
                <li aria-current="page" class="font-medium text-[#0A0F1F]">{{ $page['title'] }}</li>
            </ol>
        </nav>

        <header class="mt-6 max-w-2xl">
            <h1 class="text-[28px] font-bold tracking-tight text-[#0A0F1F] sm:text-4xl">{{ $page['title'] }}</h1>
            <span aria-hidden="true" class="mt-3 block h-1 w-14 rounded-full bg-[#F59E0B]"></span>
            @if ($updatedAt)
                <p class="mt-4 text-sm text-[#6B7280]">Terakhir diperbarui {{ $updatedAt->translatedFormat('j F Y') }}</p>
            @endif
        </header>

        {{-- Isi dari Markdown admin; HTML mentah sudah di-escape oleh LegalPages::html. --}}
        <article class="mt-8 rounded-2xl border border-[#E5E7EB] bg-white p-5 text-[15px] leading-relaxed text-[#374151] sm:p-8
            [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-[#0A0F1F] [&_h2:first-child]:mt-0 sm:[&_h2]:text-xl
            [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-[#0A0F1F]
            [&_p]:mt-3 [&_p:first-child]:mt-0 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:ps-5 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ol]:ps-5
            [&_li]:marker:text-[#F59E0B] [&_strong]:font-semibold [&_strong]:text-[#0A0F1F] [&_a]:font-medium [&_a]:text-[#B45309] [&_a]:underline [&_a]:underline-offset-2">
            {!! $html !!}
        </article>

        @if ($others->isNotEmpty())
            <p class="mt-6 text-sm text-[#6B7280]">
                Lihat juga:
                @foreach ($others as $other)
                    <a href="{{ $other['url'] }}" class="font-medium text-[#B45309] transition hover:opacity-80">{{ $other['title'] }}</a>@if (! $loop->last), @endif
                @endforeach
            </p>
        @endif
    </main>
@endsection
