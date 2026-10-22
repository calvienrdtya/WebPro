<?php

use App\Models\Account;
use App\Models\AiConversation;
use App\Models\CustomerReport;
use App\Models\Faq;
use App\Models\KnowledgeAttachment;
use App\Models\KnowledgeEntry;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReportAttachment;
use App\Models\Review;
use App\Models\ServiceFee;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Variant;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Models\WarrantyClaim;

/*
 * Log Aktivitas: teks yang tampil di menu admin Log Aktivitas.
 *
 * - routes: aksi yang dicatat, per nama rute => [modul, kalimat]. Rute yang tidak ada di sini tidak dicatat
 *   (mis. chat AI, cek voucher, pratinjau). Nama subjek (kode pesanan, nama produk, ...) ditambahkan di belakang kalimat.
 * - commands: perintah terjadwal; dicatat hanya bila benar-benar mengubah data.
 * - models: nama model dan kolom yang dipakai sebagai label subjek di daftar perubahan.
 * - masked: kolom yang nilainya tidak pernah disimpan (hanya ditulis "diubah").
 */

return [

    'modules' => [
        'auth' => 'Akun & login',
        'products' => 'Produk',
        'variants' => 'Varian',
        'warranties' => 'Garansi',
        'service_fees' => 'Biaya layanan',
        'accounts' => 'Stok akun',
        'orders' => 'Pesanan',
        'claims' => 'Klaim garansi',
        'reports' => 'Laporan',
        'vouchers' => 'Voucher',
        'users' => 'User',
        'reviews' => 'Ulasan',
        'ai' => 'AI',
        'content' => 'Konten toko',
        'settings' => 'Pengaturan',
        'logs' => 'Log',
        'backups' => 'Backup',
        'system' => 'Sistem',
    ],

    'routes' => [
        // Admin: katalog
        'admin.products.store' => ['products', 'Menambah produk'],
        'admin.products.update' => ['products', 'Mengubah produk'],
        'admin.products.destroy' => ['products', 'Menghapus produk'],
        'admin.products.toggle' => ['products', 'Mengubah status aktif produk'],
        'admin.products.image' => ['products', 'Mengubah versi gambar produk'],
        'admin.variants.store' => ['variants', 'Menambah varian'],
        'admin.variants.update' => ['variants', 'Mengubah varian'],
        'admin.variants.destroy' => ['variants', 'Menghapus varian'],
        'admin.variants.toggle' => ['variants', 'Mengubah status aktif varian'],
        'admin.warranties.store' => ['warranties', 'Menambah garansi'],
        'admin.warranties.update' => ['warranties', 'Mengubah garansi'],
        'admin.warranties.destroy' => ['warranties', 'Menghapus garansi'],
        'admin.warranties.toggle' => ['warranties', 'Mengubah status aktif garansi'],
        'admin.service-fees.store' => ['service_fees', 'Menambah biaya layanan'],
        'admin.service-fees.update' => ['service_fees', 'Mengubah biaya layanan'],
        'admin.service-fees.destroy' => ['service_fees', 'Menghapus biaya layanan'],
        'admin.service-fees.toggle' => ['service_fees', 'Mengubah status aktif biaya layanan'],
        'admin.accounts.store' => ['accounts', 'Menambah stok akun'],
        'admin.accounts.update' => ['accounts', 'Mengubah stok akun'],
        'admin.accounts.destroy' => ['accounts', 'Menghapus stok akun'],
        'admin.accounts.bulk-destroy' => ['accounts', 'Menghapus stok akun (massal)'],
        'admin.accounts.bulk-status' => ['accounts', 'Mengubah status stok akun (massal)'],

        // Admin: transaksi
        'admin.orders.confirm' => ['orders', 'Mengonfirmasi pembayaran pesanan'],
        'admin.orders.reject' => ['orders', 'Menolak pembayaran pesanan'],
        'admin.orders.restore' => ['orders', 'Memulihkan pesanan kedaluwarsa'],
        'admin.orders.note' => ['orders', 'Mengubah catatan pesanan'],
        'admin.orders.deliver' => ['orders', 'Mengirim akun pesanan'],
        'admin.orders.resend' => ['orders', 'Mengirim ulang email akun pesanan'],
        'admin.orders.replace' => ['claims', 'Mengganti akun (klaim garansi) pesanan'],
        'admin.claims.cancel' => ['claims', 'Membatalkan klaim garansi'],
        'admin.reports.claim' => ['claims', 'Membuka klaim garansi dari laporan'],
        'admin.workshop.restock' => ['claims', 'Mengembalikan akun bengkel ke stok'],
        'admin.reports.resolve' => ['reports', 'Menyelesaikan laporan'],
        'admin.reports.reopen' => ['reports', 'Membuka kembali laporan'],
        'admin.reports.destroy' => ['reports', 'Menghapus laporan'],
        'admin.reports.bulk-resolve' => ['reports', 'Menyelesaikan laporan (massal)'],
        'admin.reports.bulk-destroy' => ['reports', 'Menghapus laporan (massal)'],
        'admin.vouchers.store' => ['vouchers', 'Menambah voucher'],
        'admin.vouchers.update' => ['vouchers', 'Mengubah voucher'],
        'admin.vouchers.destroy' => ['vouchers', 'Menghapus voucher'],
        'admin.vouchers.toggle' => ['vouchers', 'Mengubah status aktif voucher'],
        'admin.vouchers.bulk-destroy' => ['vouchers', 'Menghapus voucher (massal)'],
        'admin.vouchers.bulk-active' => ['vouchers', 'Mengubah status aktif voucher (massal)'],

        // Admin: pengguna
        'admin.users.store' => ['users', 'Menambah user'],
        'admin.users.update' => ['users', 'Mengubah user'],
        'admin.users.destroy' => ['users', 'Menghapus user'],
        'admin.users.block' => ['users', 'Mengubah status blokir user'],
        'admin.users.bulk-destroy' => ['users', 'Menghapus user (massal)'],
        'admin.users.bulk-block' => ['users', 'Mengubah status blokir user (massal)'],
        'admin.reviews.status' => ['reviews', 'Mengubah status ulasan'],
        'admin.reviews.destroy' => ['reviews', 'Menghapus ulasan'],
        'admin.reviews.bulk-status' => ['reviews', 'Mengubah status ulasan (massal)'],
        'admin.reviews.bulk-destroy' => ['reviews', 'Menghapus ulasan (massal)'],

        // Admin: AI
        'admin.ai.connection.update' => ['ai', 'Mengubah koneksi AI'],
        'admin.ai.advanced.update' => ['ai', 'Mengubah pengaturan lanjutan AI'],
        'admin.ai.behavior.update' => ['ai', 'Mengubah perilaku AI'],
        'admin.ai.templates.update' => ['ai', 'Mengubah template prompt AI'],
        'admin.ai.knowledge.store' => ['ai', 'Menambah pengetahuan AI'],
        'admin.ai.knowledge.update' => ['ai', 'Mengubah pengetahuan AI'],
        'admin.ai.knowledge.destroy' => ['ai', 'Menghapus pengetahuan AI'],
        'admin.ai.knowledge.toggle' => ['ai', 'Mengubah status aktif pengetahuan AI'],
        'admin.ai.knowledge.approve' => ['ai', 'Menyetujui pengetahuan AI'],
        'admin.ai.knowledge.reprocess' => ['ai', 'Memproses ulang pengetahuan AI'],
        'admin.ai.knowledge.section-toggle' => ['ai', 'Mengubah bagian pengetahuan AI'],
        'admin.ai.knowledge.bulk-destroy' => ['ai', 'Menghapus pengetahuan AI (massal)'],
        'admin.ai.knowledge.bulk-active' => ['ai', 'Mengubah status aktif pengetahuan AI (massal)'],
        'admin.ai.conversations.destroy' => ['ai', 'Menghapus percakapan AI'],
        'admin.ai.conversations.bulk-destroy' => ['ai', 'Menghapus percakapan AI (massal)'],

        // Admin: konten dan pengaturan
        'admin.faqs.store' => ['content', 'Menambah FAQ'],
        'admin.faqs.update' => ['content', 'Mengubah FAQ'],
        'admin.faqs.destroy' => ['content', 'Menghapus FAQ'],
        'admin.faqs.toggle' => ['content', 'Mengubah status aktif FAQ'],
        'admin.faqs.move' => ['content', 'Mengubah urutan FAQ'],
        'admin.faqs.bulk-destroy' => ['content', 'Menghapus FAQ (massal)'],
        'admin.faqs.bulk-active' => ['content', 'Mengubah status aktif FAQ (massal)'],
        'admin.legal.update' => ['content', 'Mengubah halaman legal'],
        'admin.appearance.identity' => ['content', 'Mengubah identitas toko'],
        'admin.appearance.home' => ['content', 'Mengubah beranda toko'],
        'admin.appearance.reasons' => ['content', 'Mengubah bagian Kenapa memilih kami'],
        'admin.appearance.socials' => ['content', 'Mengubah media sosial toko'],
        'admin.appearance.campus.store' => ['content', 'Menambah logo kampus'],
        'admin.appearance.campus.destroy' => ['content', 'Menghapus logo kampus'],
        'admin.appearance.campus.move' => ['content', 'Mengubah urutan logo kampus'],
        'admin.appearance.image' => ['content', 'Mengubah versi gambar toko'],
        'admin.appearance.campus.image' => ['content', 'Mengubah versi logo kampus'],
        'admin.settings.notifications' => ['settings', 'Mengubah notifikasi admin'],
        'admin.settings.chat' => ['settings', 'Mengubah pop up chat'],
        'admin.settings.renewal' => ['settings', 'Mengubah pengingat perpanjangan'],
        'admin.settings.logs' => ['settings', 'Mengubah masa simpan log'],
        'admin.settings.images' => ['settings', 'Mengubah kualitas gambar'],
        'admin.settings.qris' => ['settings', 'Mengganti gambar QRIS'],
        'admin.settings.qris-reset' => ['settings', 'Mengembalikan gambar QRIS bawaan'],
        'admin.logs.system.destroy' => ['logs', 'Menghapus file log sistem'],
        'admin.backups.store' => ['backups', 'Membuat backup database'],
        'admin.backups.settings' => ['backups', 'Mengubah pengaturan backup'],
        'admin.backups.download' => ['backups', 'Mengunduh backup database'],
        'admin.backups.destroy' => ['backups', 'Menghapus backup database'],
        'admin.logs.system.download' => ['logs', 'Mengunduh file log sistem'],

        // Toko (pelanggan)
        'checkout.store' => ['orders', 'Membuat pesanan'],
        'orders.claim' => ['orders', 'Menyatakan sudah membayar pesanan'],
        'orders.cancel' => ['orders', 'Membatalkan pesanan'],
        'reports.store' => ['reports', 'Mengirim laporan masalah'],
        'reviews.store' => ['reviews', 'Mengirim ulasan'],
        'vouchers.claim' => ['vouchers', 'Mengklaim voucher'],
        'profil.update' => ['auth', 'Mengubah profil'],
        'profil.password' => ['auth', 'Mengganti sandi'],
        'profil.destroy' => ['auth', 'Menghapus akun sendiri'],
        'password.update' => ['auth', 'Mengatur ulang sandi lewat lupa sandi'],
    ],

    // Rute GET yang tetap dicatat (unduhan file log, klaim voucher lewat tautan).
    'get_routes' => ['admin.logs.system.download', 'admin.backups.download', 'vouchers.claim'],

    'commands' => [
        'accounts:expire' => ['system', 'Menandai stok akun yang habis masa aktifnya'],
        'orders:expire' => ['system', 'Mengedaluwarsakan pesanan yang lewat batas bayar'],
        'orders:deliver-due' => ['system', 'Mengirim akun bulanan yang jatuh tempo'],
        'orders:remind-renewal' => ['system', 'Mengirim email pengingat perpanjangan'],
        'backup:database' => ['backups', 'Membuat backup database terjadwal'],
        'ai:prune-chat-images' => ['system', 'Menghapus gambar chat AI yang lewat masa simpan'],
    ],

    'models' => [
        Account::class => ['Stok akun', 'email'],
        AiConversation::class => ['Percakapan AI', 'id'],
        CustomerReport::class => ['Laporan', 'subject'],
        Faq::class => ['FAQ', 'question'],
        KnowledgeEntry::class => ['Pengetahuan AI', 'title'],
        KnowledgeAttachment::class => ['Lampiran pengetahuan', 'original_name'],
        Order::class => ['Pesanan', 'code'],
        OrderDelivery::class => ['Kiriman akun', 'id'],
        Product::class => ['Produk', 'name'],
        ProductVariant::class => ['Varian produk', 'id'],
        ReportAttachment::class => ['Lampiran laporan', 'id'],
        Review::class => ['Ulasan', 'id'],
        ServiceFee::class => ['Biaya layanan', 'name'],
        Setting::class => ['Pengaturan', 'key'],
        User::class => ['User', 'email'],
        UserVoucher::class => ['Voucher user', 'id'],
        Variant::class => ['Varian', 'title'],
        Voucher::class => ['Voucher', 'code'],
        Warranty::class => ['Garansi', 'name'],
        WarrantyClaim::class => ['Klaim garansi', 'id'],
    ],

    // ---------- Tampilan daftar perubahan (data tersimpan tetap mentah; diterjemahkan saat ditampilkan) ----------

    // Nama kolom yang tampil di admin. Kolom yang tidak ada di sini tampil apa adanya.
    'fields' => [
        'name' => 'Nama', 'email' => 'Email', 'phone' => 'Nomor WhatsApp', 'password' => 'Sandi', 'pin' => 'PIN',
        'is_admin' => 'Admin', 'blocked_at' => 'Diblokir pada', 'email_verified_at' => 'Email terverifikasi', 'avatar' => 'Foto profil',
        'google_id' => 'Akun Google', 'code' => 'Kode', 'title' => 'Judul', 'description' => 'Deskripsi', 'slug' => 'Slug URL',
        'category' => 'Kategori', 'account_type' => 'Jenis akun', 'highlights' => 'Keunggulan', 'specs' => 'Spesifikasi',
        'price' => 'Harga', 'discount_percent' => 'Diskon (%)', 'service_fee' => 'Biaya layanan', 'image' => 'Gambar',
        'meta_title' => 'Judul SEO', 'meta_description' => 'Deskripsi SEO', 'sort_order' => 'Urutan', 'is_active' => 'Aktif',
        'deleted_at' => 'Dihapus pada', 'product_id' => 'Produk', 'variant_id' => 'Varian', 'price_mode' => 'Mode harga',
        'months' => 'Jumlah bulan', 'duration_days' => 'Durasi (hari)', 'applies_to_all' => 'Berlaku untuk semua produk',
        'amount' => 'Nominal', 'profile' => 'Profil', 'cogs' => 'HPP', 'status' => 'Status', 'started_at' => 'Mulai aktif',
        'active_days' => 'Masa aktif (hari)', 'sold_to' => 'Terjual ke', 'sold_at' => 'Terjual pada', 'expires_at' => 'Berakhir',
        'note' => 'Catatan', 'user_id' => 'User', 'product_name' => 'Nama produk', 'variant_title' => 'Nama varian',
        'customer_name' => 'Nama pelanggan', 'customer_email' => 'Email pelanggan', 'customer_phone' => 'WhatsApp pelanggan',
        'list_price' => 'Harga coret', 'product_price' => 'Harga produk', 'warranty_id' => 'Garansi', 'warranty_name' => 'Nama garansi',
        'warranty_days' => 'Masa garansi (hari)', 'warranty_price' => 'Harga garansi', 'voucher_id' => 'Voucher',
        'voucher_code' => 'Kode voucher', 'voucher_discount' => 'Potongan voucher', 'total' => 'Total',
        'claimed_paid_at' => 'Mengaku bayar pada', 'paid_at' => 'Lunas pada', 'renewal_reminded_at' => 'Diingatkan perpanjang pada',
        'proof_path' => 'Bukti transfer', 'verified_by' => 'Diverifikasi oleh', 'reject_reason' => 'Alasan ditolak',
        'admin_note' => 'Catatan admin', 'order_id' => 'Pesanan', 'sequence' => 'Bulan ke', 'due_on' => 'Jatuh tempo',
        'account_id' => 'Akun', 'delivered_at' => 'Terkirim pada', 'stock_alerted_at' => 'Peringatan stok pada',
        'warranty_ends_on' => 'Garansi berakhir', 'subject' => 'Judul', 'body' => 'Isi', 'resolution' => 'Penyelesaian',
        'resolution_note' => 'Catatan penyelesaian', 'duplicate_of_id' => 'Duplikat dari', 'resolved_by' => 'Diselesaikan oleh',
        'resolved_at' => 'Selesai pada', 'reviewer_name' => 'Nama pengulas', 'rating' => 'Rating', 'question' => 'Pertanyaan',
        'answer' => 'Jawaban', 'show_on_home' => 'Tampil di beranda', 'show_on_product' => 'Tampil di halaman produk',
        'discount_type' => 'Jenis potongan', 'discount_value' => 'Nilai potongan', 'max_discount' => 'Potongan maksimal',
        'min_purchase' => 'Minimal belanja', 'starts_on' => 'Mulai berlaku', 'ends_on' => 'Berakhir', 'quota' => 'Kuota',
        'used_count' => 'Terpakai', 'per_user_limit' => 'Batas per user', 'requires_claim' => 'Harus diklaim dulu',
        'is_listed' => 'Tampil di halaman voucher', 'claimed_at' => 'Diklaim pada', 'delivery_id' => 'Kiriman',
        'report_id' => 'Laporan', 'source' => 'Sumber', 'mode' => 'Mode', 'reason' => 'Alasan', 'old_account_id' => 'Akun lama',
        'new_account_id' => 'Akun pengganti', 'outside_warranty' => 'Di luar garansi', 'claim_cost' => 'Biaya klaim',
        'handled_by' => 'Ditangani oleh', 'replaced_at' => 'Diganti pada', 'raw_text' => 'Teks asli', 'is_builtin' => 'Bawaan',
        'use_ai' => 'Dirapikan AI', 'with_qa' => 'Dengan tanya jawab', 'error' => 'Galat', 'processed_at' => 'Diproses pada',
        'created_by' => 'Dibuat oleh', 'entry_id' => 'Pengetahuan', 'kind' => 'Jenis', 'path' => 'File', 'original_name' => 'Nama file',
        'mime' => 'Tipe file', 'size' => 'Ukuran', 'pages' => 'Halaman', 'messages_count' => 'Jumlah pesan',
        'last_message_at' => 'Pesan terakhir', 'anonymized_at' => 'Dianonimkan pada', 'ip' => 'IP', 'key' => 'Kunci', 'value' => 'Nilai',
    ],

    // Nama pengaturan (tabel settings) yang tampil di admin; kunci yang tidak ada memakai awalan di bawahnya.
    'settings' => [
        'notify.email.enabled' => 'Notifikasi email admin',
        'notify.email.to' => 'Email tujuan notifikasi',
        'chat.ai_enabled' => 'Pop up Tanya AI',
        'chat.wa_enabled' => 'Pop up WhatsApp',
        'payment.qris_image' => 'Gambar QRIS',
        'remind.renewal.enabled' => 'Email pengingat perpanjangan',
        'remind.renewal.days' => 'Pengingat dikirim (hari sebelum habis)',
        'logs.system.days' => 'Masa simpan Log Sistem (hari)',
        'logs.activity.days' => 'Masa simpan Log Aktivitas (hari)',
        'images.quality' => 'Kualitas gambar WebP',
        'backup.auto.enabled' => 'Backup otomatis',
        'backup.auto.days' => 'Backup otomatis setiap (hari)',
        'backup.keep' => 'Jumlah backup disimpan',
        'ai.enabled' => 'AI aktif',
        'ai.base_url' => 'Alamat router AI',
        'ai.api_key' => 'API key AI',
        'ai.chat_model' => 'Model chat AI',
        'ai.vision_model' => 'Model gambar AI',
        'ai.name' => 'Nama asisten AI',
        'ai.greeting' => 'Sapaan AI',
        'ai.instructions' => 'Instruksi AI',
        'ai.fallback' => 'Jawaban cadangan AI',
    ],
    'setting_prefixes' => [
        'notify.email.type.' => 'Notifikasi email: ',
        'store.' => 'Tampilan toko: ',
        'legal.' => 'Halaman legal: ',
        'ai.adv.' => 'AI lanjutan: ',
        'ai.tpl.' => 'Template AI: ',
        'ai.' => 'AI: ',
    ],

    // Kolom yang tidak ditampilkan di daftar perubahan (tidak bermakna bagi admin).
    'hidden_fields' => ['guest_key', 'remember_token'],

    // Kolom uang: tampil sebagai Rupiah.
    'money' => ['price', 'list_price', 'product_price', 'warranty_price', 'voucher_discount', 'service_fee', 'total', 'cogs',
        'amount', 'max_discount', 'min_purchase', 'claim_cost'],

    // Kolom ID: tampil sebagai nama data yang dirujuk [model, kolom nama]. Data yang sudah terhapus tampil "#ID".
    'references' => [
        'product_id' => [Product::class, 'name'],
        'variant_id' => [Variant::class, 'title'],
        'warranty_id' => [Warranty::class, 'name'],
        'voucher_id' => [Voucher::class, 'code'],
        'order_id' => [Order::class, 'code'],
        'account_id' => [Account::class, 'email'],
        'old_account_id' => [Account::class, 'email'],
        'new_account_id' => [Account::class, 'email'],
        'report_id' => [CustomerReport::class, 'subject'],
        'duplicate_of_id' => [CustomerReport::class, 'subject'],
        'entry_id' => [KnowledgeEntry::class, 'title'],
        'user_id' => [User::class, 'email'],
        'sold_to' => [User::class, 'email'],
        'verified_by' => [User::class, 'name'],
        'resolved_by' => [User::class, 'name'],
        'handled_by' => [User::class, 'name'],
        'created_by' => [User::class, 'name'],
    ],

    // Nilai teks per model yang bukan enum (enum memakai label() miliknya sendiri).
    'values' => [
        CustomerReport::class => ['status' => ['open' => 'Menunggu', 'resolved' => 'Selesai']],
        Review::class => ['status' => ['pending' => 'Menunggu', 'approved' => 'Tampil', 'hidden' => 'Disembunyikan']],
        WarrantyClaim::class => [
            'status' => ['open' => 'Menunggu', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan'],
            'mode' => ['stock' => 'Akun stok', 'replacement' => 'Akun replacement'],
            'source' => ['order' => 'Dari pesanan', 'report' => 'Dari laporan'],
        ],
        Voucher::class => ['discount_type' => ['percent' => 'Persen', 'fixed' => 'Nominal tetap']],
    ],

    // Kolom rahasia: nilainya tidak disimpan. Pengaturan dengan kunci ini juga disamarkan.
    'masked' => ['password', 'pin', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'google_id', 'reset_code'],
    'masked_settings' => ['ai.api_key'],

    // Kolom yang tidak perlu dicatat di daftar perubahan.
    'ignored' => ['created_at', 'updated_at'],

    // Batas isi agar satu catatan tidak membengkak (aksi massal): jumlah perubahan dan panjang nilai.
    'max_changes' => 30,
    'max_value_length' => 300,
];
