<?php

/*
 * Nilai BAWAAN menu AI. Semua nilai di sini bisa diubah admin (menu AI > Koneksi dan Perilaku) dan yang tersimpan di
 * database selalu menang; file ini hanya dipakai bila admin belum mengubahnya atau menekan "Pakai bawaan".
 */
return [
    'base_url' => env('AI_BASE_URL', 'http://173.249.63.40:20128/v1'),

    'behavior' => [
        'name' => 'Asisten ProAkses',
        'max_tokens' => 600,
        'fallback' => 'Maaf, saya belum punya informasi tentang itu. Silakan hubungi admin lewat WhatsApp supaya dibantu langsung.',
        // Pesan pertama di widget chat toko.
        'greeting' => 'Halo! Saya {nama}. Tanyakan soal produk, cara order, pembayaran, garansi, atau pesananmu ya.',
        // Balasan saat batas harian (menu Perilaku) habis; {batas} = jumlah pesan per hari.
        'limit_message' => 'Batas {batas} pertanyaan hari ini sudah habis. Silakan coba lagi besok, atau hubungi admin lewat WhatsApp.',
        'limit_image_message' => 'Batas {batas} gambar hari ini sudah habis. Kamu masih bisa bertanya dengan teks.',
        'limit_message_guest' => 'Batas {batas} pertanyaan hari ini untuk pengunjung sudah habis. Login untuk mendapat batas lebih banyak: {url_login}',
        'instructions' => <<<'TXT'
Kamu adalah asisten customer service toko ProAkses, toko akun premium sharing.
Jawab dalam bahasa Indonesia yang ramah, singkat, dan jelas.
Hanya jawab berdasarkan pengetahuan toko yang diberikan. Jangan mengarang harga, stok, promo, atau kebijakan.
Jangan pernah meminta kata sandi, kode OTP, atau data pribadi pelanggan.
Untuk masalah pesanan tertentu, arahkan pelanggan ke halaman Pesanan Saya atau hubungi admin lewat WhatsApp.
TXT,
        'limits' => [
            'guest_messages' => 20,
            'guest_images' => 2,
            'user_messages' => 30,
            'user_images' => 5,
        ],
    ],

    /*
     * Template prompt. Penanda {..} diisi PromptBuilder; daftar penanda yang sah per template ada di 'placeholders'.
     */
    'templates' => [
        'system' => <<<'TXT'
Namamu {nama}. Hari ini {tanggal}.

{instruksi}

Aturan jawaban:
- Tulis sebagai pesan chat biasa untuk pelanggan. Jangan menambahkan catatan teknis, catatan "skipped", atau komentar tentang dirimu sebagai model.
- Bila jawabannya tidak ada di pengetahuan toko, balas dengan: {pesan_tidak_tahu}
- Bila pelanggan perlu bantuan admin, arahkan untuk menghubungi admin lewat WhatsApp. Tombol WhatsApp muncul otomatis di bawah jawaban, jadi tidak perlu menulis nomor atau tautannya.
- Bila pelanggan mengeluh tetapi keterangannya kurang jelas, jangan langsung menebak. Tanyakan dulu hal yang belum jelas (produk apa, kode pesanan, apa yang terjadi atau pesan error yang muncul), cukup satu atau dua pertanyaan singkat.
- Bila keluhan sudah jelas dan berupa masalah akun atau pesanan yang perlu ditangani admin, arahkan pelanggan membuat laporan di halaman Lapor Masalah: {url_lapor}
- Bila pelanggan mengirim gambar, jelaskan apa yang terlihat dan kaitkan dengan pengetahuan toko.

=== PENGETAHUAN TOKO ===
{pengetahuan}

=== DATA PENANYA ===
{data_penanya}
TXT,
        // Isi {data_penanya} bila penanya belum login.
        'asker_guest' => <<<'TXT'
Penanya belum login, jadi kamu tidak punya data pesanan, akun, garansi, atau laporan miliknya.
Bila penanya bertanya tentang pesanannya, status pembayaran, akun yang dibeli, garansi miliknya, atau hal lain yang butuh login, jawab bahwa ia perlu login dulu untuk mengeceknya, lalu sebutkan halaman login: {url_login}
TXT,
        // Isi {data_penanya} bila penanya sudah login. {pesanan} = daftar pesanan terakhirnya (tanpa email, password, atau PIN akun).
        'asker_user' => <<<'TXT'
Penanya sudah login dengan nama {nama_pelanggan}.
Pesanan terakhirnya (paling baru di atas):
{pesanan}

Pakai data ini hanya untuk menjawab pertanyaan penanya tentang pesanannya sendiri. Jangan menebak data yang tidak ada di sini; detail akun (email, password, PIN) hanya bisa dilihat penanya di halaman pesanannya ({url_pesanan}).
TXT,
        'test_question' => 'Halo, kamu siapa? Jawab dalam satu kalimat pendek.',
        'vision_test' => 'Tuliskan angka yang terlihat di gambar ini. Balas angkanya saja.',
        // Mengubah teks mentah + lampiran (gambar/PDF) dari admin menjadi Markdown di menu Pengetahuan.
        'knowledge_convert' => <<<'TXT'
Kamu membantu admin toko ProAkses menyusun pengetahuan untuk asisten customer service.
Ubah bahan di bawah (teks dari admin dan lampiran gambar atau PDF bila ada) menjadi dokumen Markdown yang rapi dalam bahasa Indonesia.

Aturan:
- Mulai dengan judul "## {judul}".
- Pertahankan semua fakta, langkah, angka, dan istilah persis seperti di bahan. Jangan menambah informasi yang tidak ada di bahan.
- Pakai subjudul (###), daftar bernomor untuk langkah-langkah, dan tabel bila datanya berbentuk tabel.
- Bila ada gambar tangkapan layar, buat bagian "### Ciri-ciri di layar" berisi teks persis yang terlihat di layar, supaya bisa dicocokkan dengan screenshot dari pelanggan.
- Tulis dari sudut pandang toko kepada pelanggan. Bila bahan menyebut admin akan melakukan sesuatu (mis. mengirim kode), arahkan pelanggan untuk menghubungi admin lewat WhatsApp.
- Balas hanya isi Markdown, tanpa kalimat pembuka, penutup, atau catatan lain.

Produk terkait: {produk}

Teks dari admin:
{teks}
TXT,
        // Ditambahkan ke perintah konversi bila saklar "Buat tanya-jawab" menyala.
        'knowledge_qa' => <<<'TXT'
Di bagian akhir, tambahkan "### Tanya-jawab" berisi 3 sampai 8 pertanyaan yang mungkin diketik pelanggan dengan bahasa sehari-hari, masing-masing dengan jawabannya, memakai format:
- **T:** pertanyaan
  **J:** jawaban
TXT,
    ],

    'placeholders' => [
        'system' => ['nama', 'tanggal', 'instruksi', 'pesan_tidak_tahu', 'whatsapp', 'pengetahuan', 'url_lapor', 'data_penanya'],
        'asker_guest' => ['url_login'],
        'asker_user' => ['nama_pelanggan', 'pesanan', 'url_pesanan'],
        'test_question' => [],
        'vision_test' => [],
        'knowledge_convert' => ['judul', 'produk', 'teks'],
        'knowledge_qa' => [],
    ],

    /* Penanda yang wajib ada; tanpa {pengetahuan} AI tidak pernah menerima data toko. */
    'required_placeholders' => [
        'system' => ['pengetahuan'],
        'knowledge_convert' => ['teks'],
    ],

    /*
     * Pengaturan lanjutan: [bawaan, minimal, maksimal]. Bisa diubah di menu Koneksi > Pengaturan lanjutan.
     */
    'advanced' => [
        // Ruang tambahan di atas batas panjang jawaban untuk model yang "berpikir" (token berpikir dihitung di max_tokens).
        'thinking_headroom' => [2048, 0, 16000],
        // Batas waktu satu permintaan ke router (detik) dan batas waktu membuka koneksi.
        'request_timeout' => [60, 5, 180],
        'connect_timeout' => [10, 2, 60],
        // Batas panjang jawaban saat tes di menu Koneksi.
        'test_tokens' => [200, 50, 2000],
        // Jumlah pesan terakhir dari riwayat percakapan yang ikut dikirim.
        'history_messages' => [10, 0, 50],

        // Chat toko: pesan per menit per penanya (pengaman spam, di luar batas harian di menu Perilaku).
        'chat_per_minute' => [10, 1, 60],
        // Gambar dari pelanggan di chat: ukuran maksimal (MB) dan lama disimpan sebelum dihapus otomatis (hari).
        'chat_image_mb' => [5, 1, 10],
        'chat_image_days' => [30, 1, 365],
        // Percakapan user login dilanjutkan bila pesan terakhirnya belum lewat sekian jam; setelahnya mulai percakapan baru.
        'conversation_idle_hours' => [24, 1, 720],
        // Jumlah pesanan terakhir user login yang diberikan ke AI.
        'asker_orders' => [5, 1, 20],

        // Pengetahuan: konversi entri oleh AI (batas jawaban dan waktu tunggu lebih besar dari chat).
        'convert_tokens' => [4000, 500, 16000],
        'convert_timeout' => [180, 30, 600],
        // Lama isi pengetahuan disimpan di cache (detik); stok dan harga paling lambat terbarui setelah selang ini.
        'knowledge_cache_seconds' => [60, 0, 3600],
        // Perkiraan beban tersembunyi dari penyedia model per pesan (token), untuk meteran ukuran pengetahuan.
        'provider_overhead' => [2500, 0, 50000],
        // Batas total token yang dianggap aman dikirim utuh setiap pesan; di atas ini muncul peringatan.
        'full_context_limit' => [30000, 1000, 1000000],
        // Lampiran entri pengetahuan.
        'max_images' => [5, 1, 10],
        'image_mb' => [5, 1, 10],
        'pdf_mb' => [10, 1, 20],
        'pdf_pages' => [30, 1, 100],
    ],

    /* Bagian data toko otomatis di menu Pengetahuan (urutan = urutan di prompt). Tiap bagian bisa dimatikan admin. */
    'knowledge_sections' => [
        'products' => 'Produk, harga, dan stok',
        'warranties' => 'Garansi',
        'service_fee' => 'Biaya layanan',
        'vouchers' => 'Voucher',
        'faqs' => 'FAQ',
        'sales' => 'Jumlah terjual per produk',
    ],

    /* Jawaban AI yang memuat salah satu teks ini (atau nomor WhatsApp admin) diberi tombol "Chat WhatsApp" di widget. */
    'whatsapp_triggers' => ['whatsapp', 'wa.me', 'hubungi admin', 'chat admin', 'kontak admin'],

    /* Baris jawaban yang diawali teks ini dibuang sebelum sampai ke pelanggan (kebocoran instruksi penyedia). */
    'strip_prefixes' => ['skipped:'],
];
