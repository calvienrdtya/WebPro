<?php

/*
 * Nilai BAWAAN menu Tampilan Toko. Yang disimpan admin di tabel settings selalu menang (lihat App\Support\StoreProfile).
 */
return [
    'name' => env('APP_NAME', 'ProAkses'),
    'tagline' => 'Akun AI premium harga rupiah, dikirim setelah pembayaran terverifikasi, dengan garansi tersedia.',
    'email' => 'halo@proakses.id',
    'footer_note' => 'Dibuat untuk Gen Z Indonesia',

    // Gambar bawaan di public/; diganti unggahan admin (disk uploads).
    'logo' => 'assets/logo.png',
    'favicon' => 'favicon.ico',
    'hero_image' => 'assets/hero-section.png',
    'og_image' => 'assets/hero-section.png',

    'hero' => [
        // Kata di antara tanda * disorot emas, mis. "Nikmati *Hiburan Premium*".
        'title' => 'Nikmati *Hiburan Premium*, Dengan Harga Lebih *Hemat*',
        'subtitle' => 'Akun diproses setelah pembayaran terverifikasi, dengan garansi penggantian yang bisa kamu pilih saat checkout.',
        // Label kecil di atas judul; kosong = tidak tampil. {pelanggan} dan {pesanan} diganti angka nyata.
        'badge' => '',
        'alt' => 'ProAkses: akun premium harga rupiah',
        // Chip statistik dari angka nyata; otomatis tersembunyi bila angkanya 0.
        'stat_orders' => true,
        'stat_rating' => true,
    ],

    // Bagian "Dipercaya Gen Z & mahasiswa..." di beranda.
    'campus' => [
        'enabled' => true,
        'title' => 'Dipercaya Gen Z & mahasiswa dari kampus-kampus di seluruh Indonesia',
        'logos' => [
            ['assets/client/ui.png', 'Universitas Indonesia'], ['assets/client/itb.png', 'Institut Teknologi Bandung'],
            ['assets/client/its.png', 'Institut Teknologi Sepuluh Nopember'], ['assets/client/ugm.png', 'Universitas Gadjah Mada'],
            ['assets/client/undip.png', 'Universitas Diponegoro'], ['assets/client/unesa.jpeg', 'Universitas Negeri Surabaya'],
            ['assets/client/um.png', 'Universitas Negeri Malang'], ['assets/client/telkom.png', 'Universitas Telkom'],
            ['assets/client/unair.png', 'Universitas Airlangga'], ['assets/client/binus.jpg', 'Universitas Bina Nusantara'],
        ],
    ],

    // Bagian "Kenapa memilih kami" di beranda. Ikon dipilih dari reason_icons (bukan unggahan SVG, demi keamanan).
    'reasons' => [
        'enabled' => true,
        'badge' => 'Kenapa ProAkses?',
        'title' => 'Kenapa Harus di ProAkses?',
        'subtitle' => 'Kami bukan sekadar toko digital. Ini yang bikin ProAkses beda dari yang lain.',
        'items' => [
            ['icon' => 'bolt', 'title' => 'Pengiriman Otomatis', 'text' => 'Akun dikirim otomatis ke email kamu setelah pembayaran terverifikasi.'],
            ['icon' => 'shield', 'title' => 'Garansi Tersedia', 'text' => 'Pilih paket garansi saat checkout. Akun bermasalah selama masa garansi diganti gratis.'],
            ['icon' => 'lock', 'title' => 'Pembayaran Aman', 'text' => 'Bayar lewat QRIS dari e-wallet atau m-banking apa pun. Data kamu aman.'],
            ['icon' => 'percent', 'title' => 'Harga Terjangkau', 'text' => 'Dapatkan akun premium dengan harga jauh lebih murah dari langganan resmi.'],
            ['icon' => 'headset', 'title' => 'CS Responsif', 'text' => 'Tim customer service siap membantu kamu lewat WhatsApp kapan pun kamu butuh.'],
            ['icon' => 'users', 'title' => 'Pelanggan Puas', 'text' => 'Dipercaya ribuan pelanggan di seluruh Indonesia sejak 2024.'],
        ],
    ],
    'reason_icons' => [
        'bolt' => ['Petir', '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"/>'],
        'shield' => ['Perisai', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>'],
        'lock' => ['Gembok', '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
        'percent' => ['Diskon', '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>'],
        'headset' => ['Headset', '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>'],
        'users' => ['Pengguna', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>'],
        'clock' => ['Jam', '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
        'star' => ['Bintang', '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'],
        'gift' => ['Hadiah', '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>'],
        'heart' => ['Hati', '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>'],
        'check' => ['Centang', '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>'],
        'refresh' => ['Putar ulang', '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M8 16H3v5"/>'],
    ],

    // Platform sosial media (urutan bawaan). Ikon Font Awesome + warna merek untuk footer.
    'social_platforms' => [
        'instagram' => ['label' => 'Instagram', 'icon' => 'fa-instagram', 'color' => '#E1306C'],
        'tiktok' => ['label' => 'TikTok', 'icon' => 'fa-tiktok', 'color' => '#0F172A'],
        'facebook' => ['label' => 'Facebook', 'icon' => 'fa-facebook-f', 'color' => '#1877F2'],
        'x' => ['label' => 'X (Twitter)', 'icon' => 'fa-x-twitter', 'color' => '#0F172A'],
        'youtube' => ['label' => 'YouTube', 'icon' => 'fa-youtube', 'color' => '#FF0000'],
        'telegram' => ['label' => 'Telegram', 'icon' => 'fa-telegram', 'color' => '#26A5E4'],
        // Nomor dari ADMIN_WHATSAPP_NUMBER (.env), tidak diketik di admin; admin hanya mengatur tampil dan urutan.
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fa-whatsapp', 'color' => '#25D366', 'managed' => true],
    ],
    'socials' => [
        ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@proakses', 'active' => true],
        ['platform' => 'instagram', 'url' => '', 'active' => true],
        ['platform' => 'whatsapp', 'url' => '', 'active' => true],
    ],

    // Unggahan (KB).
    'max_logo_kb' => 1024,
    'max_image_kb' => 3072,
];
