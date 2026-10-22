@extends('layouts.store')

@php
    $waLink = \App\Support\Whatsapp::url('Halo '.\App\Support\StoreProfile::name().', saya mau melaporkan masalah');
    $full = $openCount >= $maxOpen;
    $alert = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>';
    $tick = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
    $serverErrors = collect(['category', 'order_id', 'subject', 'body'])->mapWithKeys(fn ($k) => [$k => $errors->first($k)]);
    $fileErrors = array_values(array_unique(array_merge($errors->get('attachments'), ...array_values($errors->get('attachments.*')))));
@endphp

@section('title', 'Lapor Masalah — '.\App\Support\StoreProfile::name())

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <main id="main" class="co-page rp-page mx-auto max-w-[1240px] px-5 py-8 sm:px-8 sm:py-12">
        <div class="rp-rise mb-8 flex items-center gap-4 sm:mb-10">
            <span class="rp-hero-icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1Z"/><path d="M4 22v-7"/></svg>
            </span>
            <div class="min-w-0">
                <h1 class="text-[26px] font-bold leading-tight tracking-tight text-[#0A0F1F] sm:text-[32px]">Lapor Masalah</h1>
                <p class="mt-1 max-w-[600px] text-sm text-[#6B7280] sm:text-[15px]">Ada kendala dengan akun, pembayaran, atau pesanan? Ceritakan di sini dan admin akan menindaklanjutinya.</p>
            </div>
        </div>

        <div class="grid items-start gap-6 md:grid-cols-[minmax(0,1.85fr)_minmax(0,1fr)] lg:gap-8">
            <div class="co-card" style="--delay:.08s">
                <form method="POST" action="{{ route('reports.store') }}" enctype="multipart/form-data" novalidate x-data="reportForm()" @submit="submit($event)">
                    @csrf

                    <section class="rp-section" aria-labelledby="h-masalah">
                        <div class="rp-head">
                            <span class="rp-head-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/><path d="M12 7v4M12 14h.01"/></svg></span>
                            <h2 id="h-masalah">Masalahnya apa?</h2>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-2">
                            <div>
                                <label for="rp-category" class="rp-label">Kategori</label>
                                <div class="rp-control">
                                    <select id="rp-category" name="category" required x-ref="category" x-model="v.category" @change="touch('category')" @blur="touch('category')"
                                        class="rp-input" :class="cls('category', { 'is-empty': !v.category })"
                                        :aria-invalid="!!e.category" aria-describedby="rp-category-err">
                                        <option value="" disabled>Pilih kategori</option>
                                        @foreach ($categories as $c)
                                            <option value="{{ $c['value'] }}">{{ $c['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <svg class="rp-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </div>
                                <p id="rp-category-err" class="rp-error" role="alert" x-show="e.category" x-cloak>{!! $alert !!}<span x-text="e.category"></span></p>
                            </div>
                            <div>
                                <label for="rp-order" class="rp-label">Pesanan terkait <small>(opsional)</small></label>
                                <div class="rp-control">
                                    <select id="rp-order" name="order_id" x-model="v.order_id" class="rp-input" :class="{ 'is-invalid': e.order_id }"
                                        :aria-invalid="!!e.order_id" aria-describedby="rp-order-err">
                                        <option value="">Tidak terkait pesanan</option>
                                        @foreach ($orders as $o)
                                            <option value="{{ $o->id }}">{{ $o->code }} · {{ $o->product_name }}</option>
                                        @endforeach
                                    </select>
                                    <svg class="rp-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </div>
                                <p id="rp-order-err" class="rp-error" role="alert" x-show="e.order_id" x-cloak>{!! $alert !!}<span x-text="e.order_id"></span></p>
                            </div>
                        </div>
                    </section>

                    <section class="rp-section" aria-labelledby="h-cerita">
                        <div class="rp-head">
                            <span class="rp-head-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></span>
                            <h2 id="h-cerita">Ceritakan detailnya</h2>
                        </div>
                        <div class="space-y-5">
                            <div>
                                <label for="rp-subject" class="rp-label">Judul</label>
                                <div class="rp-control">
                                    <input id="rp-subject" name="subject" type="text" maxlength="{{ $limits['subject_max'] }}" required x-ref="subject" x-model="v.subject"
                                        @blur="touch('subject')" @input="t.subject && check('subject')"
                                        placeholder="Contoh: Akun tidak bisa login" class="rp-input" :class="cls('subject')"
                                        :aria-invalid="!!e.subject" aria-describedby="rp-subject-err" />
                                    <span class="rp-ok" x-show="ok('subject')" x-cloak aria-hidden="true">{!! $tick !!}</span>
                                </div>
                                <p id="rp-subject-err" class="rp-error" role="alert" x-show="e.subject" x-cloak>{!! $alert !!}<span x-text="e.subject"></span></p>
                            </div>
                            <div>
                                <label for="rp-body" class="rp-label">Isi laporan</label>
                                <div class="rp-control">
                                    <textarea id="rp-body" name="body" rows="6" maxlength="{{ $limits['body_max'] }}" required x-ref="body" x-model="v.body"
                                        @blur="touch('body')" @input="t.body && check('body')"
                                        placeholder="Jelaskan masalahnya: apa yang terjadi, sejak kapan, dan pesan error yang muncul (bila ada)." class="rp-input" :class="cls('body')"
                                        :aria-invalid="!!e.body" aria-describedby="rp-body-err rp-body-count"></textarea>
                                    <span class="rp-ok" x-show="ok('body')" x-cloak aria-hidden="true">{!! $tick !!}</span>
                                    <span id="rp-body-count" class="rp-counter" :class="{ 'is-near': v.body.length >= L.body_max * .9 && v.body.length < L.body_max, 'is-over': v.body.length >= L.body_max }"><span x-text="v.body.length">0</span>/{{ $limits['body_max'] }}</span>
                                </div>
                                <p id="rp-body-err" class="rp-error" role="alert" x-show="e.body" x-cloak>{!! $alert !!}<span x-text="e.body"></span></p>
                            </div>
                        </div>
                    </section>

                    <section class="rp-section" aria-labelledby="h-bukti">
                        <div class="rp-head">
                            <span class="rp-head-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg></span>
                            <h2 id="h-bukti">Bukti <small>(opsional)</small></h2>
                        </div>
                        <input id="rp-files" x-ref="files" name="attachments[]" type="file" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" aria-hidden="true" @change="add($event.target.files)" />
                        <button type="button" class="rp-drop" :class="{ 'is-drag': drag, 'is-full': files.length >= L.files }" :aria-disabled="files.length >= L.files"
                            aria-describedby="rp-files-hint rp-files-err"
                            @click="files.length < L.files && $refs.files.click()"
                            @dragover.prevent="drag = files.length < L.files" @dragleave.prevent="drag = false" @drop.prevent="drag = false; add($event.dataTransfer.files)">
                            <span class="rp-drop-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg></span>
                            <span>
                                <span class="block text-[15px] font-semibold text-[#0A0F1F]">Pilih tangkapan layar</span>
                                <span id="rp-files-hint" class="mt-1 block text-[13px] text-[#6B7280]">JPG, PNG, atau WebP. Maksimal 3 gambar, 3 MB per gambar.</span>
                            </span>
                            <span x-show="files.length" x-cloak class="rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-[#B45309] ring-1 ring-inset ring-[#F59E0B]/40" x-text="files.length + '/' + L.files + ' gambar'"></span>
                        </button>
                        <ul x-show="files.length" x-cloak class="rp-thumbs" aria-label="Gambar terpilih">
                            <template x-for="(f, i) in files" :key="f.url">
                                <li class="rp-thumb">
                                    <img :src="f.url" :alt="f.file.name">
                                    <p x-text="f.file.name"></p>
                                    <button type="button" class="rp-thumb-del" @click="remove(i)" :aria-label="'Hapus ' + f.file.name">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <div id="rp-files-err" role="alert">
                            <template x-for="msg in fileErrors" :key="msg">
                                <p class="rp-error">{!! $alert !!}<span x-text="msg"></span></p>
                            </template>
                        </div>
                    </section>

                    @if ($full)
                        <p class="mt-7 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-inset ring-amber-200">Kamu masih punya {{ $openCount }} laporan yang menunggu. Laporan baru bisa dikirim setelah admin menyelesaikan salah satunya.</p>
                    @endif
                    <button type="submit" class="rp-submit" :disabled="sending || @js($full)" @disabled($full)>
                        <svg x-show="!sending" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        <svg x-show="sending" x-cloak class="rp-spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.2-8.6"/></svg>
                        <span x-show="!sending">Kirim Laporan</span>
                        <span x-show="sending" x-cloak>Mengirim...</span>
                    </button>
                </form>
            </div>

            <aside class="rp-tips rp-rise md:sticky md:top-24" style="--delay:.16s" aria-labelledby="h-tips">
                <div class="flex items-center gap-3">
                    <span class="rp-tips-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 22h4"/><path d="M15.1 14c.2-1 .7-1.7 1.4-2.5A6 6 0 1 0 6 8c0 1.3.5 2.6 1.5 3.5.8.8 1.3 1.5 1.5 2.5"/></svg></span>
                    <h2 id="h-tips">Supaya cepat ditangani</h2>
                </div>
                <ul class="mt-5">
                    @foreach ([
                        'Pilih pesanan terkait agar admin langsung melihat akun dan garansinya.',
                        'Sebutkan pesan error atau apa yang terjadi saat login.',
                        'Lampirkan tangkapan layar bila ada.',
                        'Jangan ubah email, password, atau PIN akun sebelum ditangani admin.',
                    ] as $tip)
                        <li><span class="rp-tick">{!! $tick !!}</span><span>{{ $tip }}</span></li>
                    @endforeach
                </ul>
                <a href="{{ route('profil', ['tab' => 'laporan']) }}" class="rp-tips-btn">
                    Lihat laporan saya
                    @if ($openCount)
                        <span class="rp-tips-badge">{{ $openCount }} menunggu</span>
                    @endif
                </a>
                <p class="rp-tips-foot">Butuh jawaban cepat? <a href="{{ $waLink }}" rel="noopener">Chat admin</a></p>
            </aside>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        function reportForm() {
            const L = @js($limits);
            const TYPES = ['image/jpeg', 'image/png', 'image/webp']
            const rules = {
                category: (v) => (v ? '' : 'Pilih kategori laporan.'),
                subject: (v) => {
                    v = v.trim()
                    if (!v) return 'Judul wajib diisi.'
                    return v.length < L.subject_min ? `Judul minimal ${L.subject_min} karakter.` : ''
                },
                body: (v) => {
                    v = v.trim()
                    if (!v) return 'Isi laporan wajib diisi.'
                    if (v.length < L.body_min) return `Isi laporan minimal ${L.body_min} karakter (sekarang ${v.length}).`
                    return v.length > L.body_max ? `Isi laporan maksimal ${L.body_max} karakter.` : ''
                },
            }
            const server = @js($serverErrors);

            return {
                L,
                v: {
                    category: @js((string) old('category', '')),
                    order_id: @js((string) ($selectedOrder ?? '')),
                    subject: @js((string) old('subject', '')),
                    body: @js((string) old('body', '')),
                },
                e: { ...server },
                t: Object.fromEntries(Object.keys(server).map((k) => [k, !!server[k]])),
                files: [],
                fileErrors: @js($fileErrors),
                drag: false,
                sending: false,
                check(k) {
                    this.e[k] = rules[k](this.v[k])
                    return !this.e[k]
                },
                touch(k) {
                    this.t[k] = true
                    this.check(k)
                },
                ok(k) {
                    return this.t[k] && !this.e[k] && this.v[k] !== ''
                },
                cls(k, extra = {}) {
                    return { 'is-invalid': this.e[k], 'is-valid': this.ok(k), ...extra }
                },
                add(list) {
                    const errs = []
                    for (const file of list) {
                        if (this.files.length >= L.files) { errs.push(`Maksimal ${L.files} gambar.`); break }
                        if (!TYPES.includes(file.type)) { errs.push(`${file.name}: format harus JPG, PNG, atau WebP.`); continue }
                        if (file.size > L.file_kb * 1024) { errs.push(`${file.name}: ukuran lebih dari ${L.file_kb / 1024} MB.`); continue }
                        this.files.push({ file, url: URL.createObjectURL(file) })
                    }
                    this.fileErrors = errs
                    this.sync()
                },
                remove(i) {
                    URL.revokeObjectURL(this.files[i].url)
                    this.files.splice(i, 1)
                    this.fileErrors = []
                    this.sync()
                },
                // Isi ulang input file dari daftar terpilih agar hapus dan tambah bertahap ikut terkirim.
                sync() {
                    const dt = new DataTransfer()
                    this.files.forEach((f) => dt.items.add(f.file))
                    this.$refs.files.files = dt.files
                },
                submit(ev) {
                    const bad = Object.keys(rules).filter((k) => (this.t[k] = true) && !this.check(k))
                    if (bad.length) {
                        ev.preventDefault()
                        this.$refs[bad[0]].focus()
                        return
                    }
                    this.sending = true
                },
            }
        }
    </script>
@endpush
