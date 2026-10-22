<?php

return [
    // false = batas kirim kode reset dimatikan (cooldown 30 detik, 5 kode/jam, 10 permintaan/menit). Diabaikan di production.
    'password_reset_throttle' => env('PASSWORD_RESET_THROTTLE', true),

    // Nomor WhatsApp admin (08xx atau 628xx). Dipakai tautan WhatsApp di seluruh toko dan widget chat.
    'whatsapp' => env('ADMIN_WHATSAPP_NUMBER'),

    // Masa aktif bawaan akun baru (hari). Bisa diubah per akun saat input.
    'account_days' => 28,

    // Batas waktu bayar setelah pesanan dibuat (menit); setelahnya pesanan kedaluwarsa dan stok dilepas.
    'payment_minutes' => 15,

    // Jumlah pesanan belum lunas (menunggu bayar/verifikasi) yang boleh dimiliki satu user sekaligus.
    'max_open_orders' => 3,

    // Selang kiriman akun bulan ke-2 dst: hari setelah akun sebelumnya terkirim.
    'delivery_interval_days' => 28,

    // Pengingat perpanjangan ke pelanggan: email sekian hari sebelum akun terakhir pesanan habis.
    // Bawaan; admin bisa mengubah saklar dan jumlah hari di Pengaturan (settings remind.renewal.*).
    'renewal_reminder' => [
        'enabled' => true,
        'days' => 3,
        'min_days' => 1,
        'max_days' => 14,
    ],

    // Email lewat antrean (App\Mail\QueuedMail): jumlah percobaan dan jeda antar percobaan (detik) bila SMTP gagal.
    // Kode lupa sandi tidak lewat antrean agar langsung sampai.
    'mail_queue' => [
        'tries' => 3,
        'backoff' => [60, 300],
    ],

    // Gambar unggahan (App\Support\ImageOptimizer): dikecilkan ke ukuran maksimal per jenis lalu disimpan WebP; file asli
    // disimpan di storage/app/private/image-originals dan bisa dipakai lagi per gambar. Kualitas bisa diubah di Pengaturan.
    // Ukuran = ukuran tampil terbesar di toko x2 (layar retina). null = tidak dibatasi pada sisi itu.
    'images' => [
        'quality' => 90,
        'min_quality' => 80,
        'max_quality' => 100,
        // Gambar lebih besar dari ini (piksel) tidak diproses agar memori PHP aman; disimpan apa adanya.
        'max_pixels' => 40_000_000,
        // Hasil dipakai hanya bila minimal 10% lebih kecil dari aslinya (hindari kompresi ulang yang tidak sebanding).
        'min_saving' => 0.1,
        'profiles' => [
            'product' => ['width' => 1050, 'height' => 1050, 'format' => 'webp', 'share' => true],
            'hero' => ['width' => 1120, 'height' => null, 'format' => 'webp', 'share' => true],
            'logo' => ['width' => 640, 'height' => 128, 'format' => 'webp', 'share' => false],
            'campus' => ['width' => 400, 'height' => 112, 'format' => 'webp', 'share' => false],
            // Gambar bagikan (og:image): JPG agar terbaca semua aplikasi pratinjau tautan.
            'share' => ['width' => 1200, 'height' => 1200, 'format' => 'jpg', 'share' => false],
        ],
        // Pratinjau tautan untuk gambar produk/hero: JPG kecil terpisah.
        'share_variant' => ['width' => 1200, 'height' => 1200, 'quality' => 85],
    ],

    // Backup database (menu Backup): bawaan; admin mengubah saklar otomatis, selang hari, dan jumlah simpan di menu itu
    // (settings backup.*). Cron memeriksa setiap hari pukul `time`.
    'backup' => [
        'enabled' => true,
        'interval_days' => 1,
        'keep' => 7,
        'max_interval_days' => 30,
        'max_keep' => 60,
        'time' => '02:30',
    ],

    // Log Sistem (file storage/logs/laravel-YYYY-MM-DD.log) dan Log Aktivitas (tabel activity_logs).
    // Masa simpan bawaan; admin bisa mengubahnya di Pengaturan (settings logs.*.days). Dihapus oleh cron logs:prune.
    'logs' => [
        'system_days' => 14,
        'activity_days' => 90,
        'min_days' => 1,
        'max_system_days' => 365,
        'max_activity_days' => 1825,
        // Entri per halaman di penampil log.
        'per_page' => 50,
        // File log lebih besar dari ini hanya dibaca bagian akhirnya (MB), agar halaman tetap ringan.
        'max_read_mb' => 20,
    ],

];
