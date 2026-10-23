<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\AiConversationController;
use App\Http\Controllers\Admin\AiController;
use App\Http\Controllers\Admin\AiKnowledgeController;
use App\Http\Controllers\Admin\AiSimulationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeployGuideController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\LegalPageController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\ServiceFeeController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StoreAppearanceController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VariantController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\WarrantyClaimController;
use App\Http\Controllers\Admin\WarrantyController;
use App\Http\Controllers\Admin\WorkshopController;
use App\Support\SystemLog;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class)->name('admin.dashboard');

// Halaman fitur admin lain memakai awalan /admin (mis. /admin/produk).
Route::redirect('/admin', '/dashboard');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('produk', [ProductController::class, 'index'])->name('products.index');
    Route::get('produk/baru', [ProductController::class, 'create'])->name('products.create');
    Route::post('produk', [ProductController::class, 'store'])->name('products.store');
    Route::get('produk/{product}/ubah', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('produk/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('produk/{product}/aktif', [ProductController::class, 'toggle'])->name('products.toggle');
    Route::patch('produk/{product}/gambar', [ProductController::class, 'image'])->name('products.image');
    Route::delete('produk/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    // Kategori dibuat otomatis dari form produk; di sini hanya ganti nama (slug dikunci).
    Route::patch('kategori-produk/{category:slug}', [ProductCategoryController::class, 'update'])->name('product-categories.update');

    // Varian: templat durasi, dipasang ke produk dengan harga per produk.
    Route::get('varian', [VariantController::class, 'index'])->name('variants.index');
    Route::post('varian', [VariantController::class, 'store'])->name('variants.store');
    Route::put('varian/{variant}', [VariantController::class, 'update'])->name('variants.update');
    Route::patch('varian/{variant}/aktif', [VariantController::class, 'toggle'])->name('variants.toggle');
    Route::delete('varian/{variant}', [VariantController::class, 'destroy'])->name('variants.destroy');

    // Garansi: paket berbayar buatan admin, dipasang ke produk.
    Route::get('garansi', [WarrantyController::class, 'index'])->name('warranties.index');
    Route::post('garansi', [WarrantyController::class, 'store'])->name('warranties.store');
    Route::put('garansi/{warranty}', [WarrantyController::class, 'update'])->name('warranties.update');
    Route::patch('garansi/{warranty}/aktif', [WarrantyController::class, 'toggle'])->name('warranties.toggle');
    Route::delete('garansi/{warranty}', [WarrantyController::class, 'destroy'])->name('warranties.destroy');

    // Biaya layanan (biaya admin) di checkout; hanya satu yang aktif dalam satu waktu.
    Route::get('biaya-layanan', [ServiceFeeController::class, 'index'])->name('service-fees.index');
    Route::post('biaya-layanan', [ServiceFeeController::class, 'store'])->name('service-fees.store');
    Route::put('biaya-layanan/{fee}', [ServiceFeeController::class, 'update'])->whereNumber('fee')->name('service-fees.update');
    Route::patch('biaya-layanan/{fee}/aktif', [ServiceFeeController::class, 'toggle'])->whereNumber('fee')->name('service-fees.toggle');
    Route::delete('biaya-layanan/{fee}', [ServiceFeeController::class, 'destroy'])->whereNumber('fee')->name('service-fees.destroy');

    // Pesanan: antrean verifikasi pembayaran QRIS manual, kirim akun, tolak, dan pulihkan yang kedaluwarsa.
    Route::get('pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::patch('pesanan/{order:id}/konfirmasi', [OrderController::class, 'confirm'])->whereNumber('order')->name('orders.confirm');
    Route::patch('pesanan/{order:id}/tolak', [OrderController::class, 'reject'])->whereNumber('order')->name('orders.reject');
    Route::patch('pesanan/{order:id}/pulihkan', [OrderController::class, 'restore'])->whereNumber('order')->name('orders.restore');
    Route::post('pesanan/{order:id}/kiriman/{delivery}/kirim-ulang', [OrderController::class, 'resend'])->whereNumber(['order', 'delivery'])->name('orders.resend');
    Route::patch('pesanan/{order:id}/catatan', [OrderController::class, 'note'])->whereNumber('order')->name('orders.note');
    Route::post('pesanan/{order:id}/kiriman/{delivery}/kirim', [OrderController::class, 'deliver'])->whereNumber(['order', 'delivery'])->name('orders.deliver');
    Route::get('pesanan/{order:id}/bukti', [OrderController::class, 'proof'])->whereNumber('order')->name('orders.proof');

    // Ganti akun (klaim garansi) dari dialog di modal pesanan atau halaman Klaim Garansi.
    Route::get('pesanan/{order:id}/pengganti', [WarrantyClaimController::class, 'options'])->whereNumber('order')->name('orders.replacements');
    Route::post('pesanan/{order:id}/ganti-akun', [WarrantyClaimController::class, 'replace'])->whereNumber('order')->name('orders.replace');

    // Klaim Garansi: catatan pelanggan yang sudah mengklaim; yang berasal dari Laporan menunggu penggantian.
    Route::get('klaim-garansi', [WarrantyClaimController::class, 'index'])->name('claims.index');
    Route::patch('klaim-garansi/{claim}/batal', [WarrantyClaimController::class, 'cancel'])->whereNumber('claim')->name('claims.cancel');
    Route::post('laporan/{report}/klaim', [WarrantyClaimController::class, 'openFromReport'])->whereNumber('report')->name('reports.claim');

    // Bengkel: akun rusak yang sudah diganti, menunggu diperbaiki lalu dikembalikan ke stok.
    Route::get('bengkel', [WorkshopController::class, 'index'])->name('workshop.index');
    Route::patch('bengkel/{claim}/stok', [WorkshopController::class, 'restock'])->whereNumber('claim')->name('workshop.restock');

    // Laporan pelanggan: satu arah, admin menyelesaikan (resolve). Hapus hanya untuk laporan yang sudah selesai.
    Route::get('laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::get('laporan/lampiran/{attachment}', [ReportController::class, 'attachment'])->whereNumber('attachment')->name('reports.attachment');
    Route::patch('laporan/massal/selesai', [ReportController::class, 'bulkResolve'])->name('reports.bulk-resolve');
    Route::delete('laporan/massal', [ReportController::class, 'bulkDestroy'])->name('reports.bulk-destroy');
    Route::patch('laporan/{report}/selesai', [ReportController::class, 'resolve'])->whereNumber('report')->name('reports.resolve');
    Route::patch('laporan/{report}/buka', [ReportController::class, 'reopen'])->whereNumber('report')->name('reports.reopen');
    Route::delete('laporan/{report}', [ReportController::class, 'destroy'])->whereNumber('report')->name('reports.destroy');

    // Ulasan: moderasi ulasan pembeli (tampilkan, sembunyikan, hapus). Isi ulasan tidak diubah admin.
    Route::get('ulasan', [ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('ulasan/massal/status', [ReviewController::class, 'bulkStatus'])->name('reviews.bulk-status');
    Route::delete('ulasan/massal', [ReviewController::class, 'bulkDestroy'])->name('reviews.bulk-destroy');
    Route::patch('ulasan/{review}/status', [ReviewController::class, 'updateStatus'])->whereNumber('review')->name('reviews.status');
    Route::delete('ulasan/{review}', [ReviewController::class, 'destroy'])->whereNumber('review')->name('reviews.destroy');

    // FAQ: pertanyaan umum yang tampil di beranda, halaman produk, dan /faq.
    Route::get('faq', [FaqController::class, 'index'])->name('faqs.index');
    Route::post('faq', [FaqController::class, 'store'])->name('faqs.store');
    Route::patch('faq/massal/aktif', [FaqController::class, 'bulkActive'])->name('faqs.bulk-active');
    Route::delete('faq/massal', [FaqController::class, 'bulkDestroy'])->name('faqs.bulk-destroy');
    Route::put('faq/{faq}', [FaqController::class, 'update'])->whereNumber('faq')->name('faqs.update');
    Route::patch('faq/{faq}/aktif', [FaqController::class, 'toggle'])->whereNumber('faq')->name('faqs.toggle');
    Route::patch('faq/{faq}/urut', [FaqController::class, 'move'])->whereNumber('faq')->name('faqs.move');
    Route::delete('faq/{faq}', [FaqController::class, 'destroy'])->whereNumber('faq')->name('faqs.destroy');

    // Pengaturan toko (saat ini: notifikasi).
    Route::get('pengaturan', [SettingController::class, 'index'])->name('settings.index');
    Route::put('pengaturan/notifikasi', [SettingController::class, 'updateNotifications'])->name('settings.notifications');
    Route::put('pengaturan/chat', [SettingController::class, 'updateChat'])->name('settings.chat');
    Route::put('pengaturan/pesan-wa', [SettingController::class, 'updateWhatsappMessages'])->name('settings.wa-messages');
    Route::put('pengaturan/pengingat', [SettingController::class, 'updateRenewal'])->name('settings.renewal');
    Route::put('pengaturan/log', [SettingController::class, 'updateLogs'])->name('settings.logs');
    Route::put('pengaturan/gambar', [SettingController::class, 'updateImages'])->name('settings.images');
    Route::post('pengaturan/qris', [SettingController::class, 'updateQris'])->name('settings.qris');
    Route::delete('pengaturan/qris', [SettingController::class, 'resetQris'])->name('settings.qris-reset');

    // AI: koneksi ke router AI (kompatibel OpenAI) dan perilaku asisten Tanya AI di toko.
    Route::redirect('ai', '/admin/ai/koneksi');
    Route::get('ai/koneksi', [AiController::class, 'connection'])->name('ai.connection');
    Route::put('ai/koneksi', [AiController::class, 'updateConnection'])->name('ai.connection.update');
    Route::post('ai/model', [AiController::class, 'models'])->middleware('throttle:20,1')->name('ai.models');
    Route::post('ai/tes', [AiController::class, 'test'])->middleware('throttle:20,1')->name('ai.test');
    Route::get('ai/perilaku', [AiController::class, 'behavior'])->name('ai.behavior');
    Route::put('ai/perilaku', [AiController::class, 'updateBehavior'])->name('ai.behavior.update');
    Route::put('ai/template', [AiController::class, 'updateTemplates'])->name('ai.templates.update');
    Route::post('ai/pratinjau', [AiController::class, 'preview'])->middleware('throttle:60,1')->name('ai.preview');
    Route::put('ai/lanjutan', [AiController::class, 'updateAdvanced'])->name('ai.advanced.update');

    // Simulasi chat (panel di halaman Koneksi): widget toko yang sama, dijawab AI dengan pengaturan tersimpan (tanpa batas harian dan log).
    Route::get('ai/simulasi', fn () => redirect('/admin/ai/koneksi'))->name('ai.simulation'); // alamat lama halaman Simulasi
    Route::post('ai/simulasi', [AiSimulationController::class, 'chat'])->middleware('throttle:30,1')->name('ai.simulation.chat');

    // Pengetahuan AI: data toko otomatis + entri admin (teks, gambar, PDF) yang dirapikan AI lalu ditinjau.
    // Percakapan: log chat Tanya AI di toko (hapus = soft delete).
    Route::get('ai/percakapan', [AiConversationController::class, 'index'])->name('ai.conversations.index');
    Route::delete('ai/percakapan/massal', [AiConversationController::class, 'bulkDestroy'])->name('ai.conversations.bulk-destroy');
    Route::get('ai/percakapan/gambar/{message}', [AiConversationController::class, 'image'])->whereNumber('message')->name('ai.conversations.image');
    Route::get('ai/percakapan/{conversation}', [AiConversationController::class, 'show'])->whereNumber('conversation')->name('ai.conversations.show');
    Route::delete('ai/percakapan/{conversation}', [AiConversationController::class, 'destroy'])->whereNumber('conversation')->name('ai.conversations.destroy');

    Route::get('ai/pengetahuan', [AiKnowledgeController::class, 'index'])->name('ai.knowledge.index');
    Route::post('ai/pengetahuan', [AiKnowledgeController::class, 'store'])->name('ai.knowledge.store');
    Route::patch('ai/pengetahuan/massal/aktif', [AiKnowledgeController::class, 'bulkActive'])->name('ai.knowledge.bulk-active');
    Route::delete('ai/pengetahuan/massal', [AiKnowledgeController::class, 'bulkDestroy'])->name('ai.knowledge.bulk-destroy');
    Route::get('ai/pengetahuan/bagian/{key}', [AiKnowledgeController::class, 'section'])->name('ai.knowledge.section');
    Route::patch('ai/pengetahuan/bagian/{key}', [AiKnowledgeController::class, 'toggleSection'])->name('ai.knowledge.section-toggle');
    Route::get('ai/pengetahuan/lampiran/{attachment}', [AiKnowledgeController::class, 'attachment'])->whereNumber('attachment')->name('ai.knowledge.attachment');
    Route::put('ai/pengetahuan/{entry}', [AiKnowledgeController::class, 'update'])->whereNumber('entry')->name('ai.knowledge.update');
    Route::post('ai/pengetahuan/{entry}/proses', [AiKnowledgeController::class, 'reprocess'])->whereNumber('entry')->name('ai.knowledge.reprocess');
    Route::patch('ai/pengetahuan/{entry}/setujui', [AiKnowledgeController::class, 'approve'])->whereNumber('entry')->name('ai.knowledge.approve');
    Route::patch('ai/pengetahuan/{entry}/aktif', [AiKnowledgeController::class, 'toggle'])->whereNumber('entry')->name('ai.knowledge.toggle');
    Route::delete('ai/pengetahuan/{entry}', [AiKnowledgeController::class, 'destroy'])->whereNumber('entry')->name('ai.knowledge.destroy');

    // Panduan Deploy: cek kesiapan production dan dokumen langkah deploy.
    Route::get('panduan-deploy', DeployGuideController::class)->name('deploy.guide');

    // Halaman Legal: Syarat & Ketentuan dan Kebijakan Privasi (Markdown) yang tampil di toko.
    Route::get('halaman-legal', [LegalPageController::class, 'index'])->name('legal.index');
    Route::put('halaman-legal/{page}', [LegalPageController::class, 'update'])->whereIn('page', array_keys(config('legal.pages')))->name('legal.update');

    // Tampilan Toko: identitas (nama, logo, favicon, footer), hero beranda, logo kampus, dan sosial media.
    Route::get('tampilan', [StoreAppearanceController::class, 'index'])->name('appearance.index');
    Route::post('tampilan/identitas', [StoreAppearanceController::class, 'updateIdentity'])->name('appearance.identity');
    Route::post('tampilan/beranda', [StoreAppearanceController::class, 'updateHome'])->name('appearance.home');
    Route::post('tampilan/kampus', [StoreAppearanceController::class, 'storeCampus'])->name('appearance.campus.store');
    Route::patch('tampilan/kampus/{index}/urut', [StoreAppearanceController::class, 'moveCampus'])->whereNumber('index')->name('appearance.campus.move');
    Route::delete('tampilan/kampus/{index}', [StoreAppearanceController::class, 'destroyCampus'])->whereNumber('index')->name('appearance.campus.destroy');
    Route::patch('tampilan/gambar/{key}', [StoreAppearanceController::class, 'image'])->whereIn('key', ['logo', 'hero_image', 'og_image'])->name('appearance.image');
    Route::patch('tampilan/kampus/{index}/gambar', [StoreAppearanceController::class, 'campusImage'])->whereNumber('index')->name('appearance.campus.image');
    Route::put('tampilan/sosmed', [StoreAppearanceController::class, 'updateSocials'])->name('appearance.socials');
    Route::put('tampilan/keunggulan', [StoreAppearanceController::class, 'updateReasons'])->name('appearance.reasons');

    // Voucher: potongan harga produk berkode, bisa diklaim pelanggan di halaman voucher.
    Route::get('voucher', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('voucher', [VoucherController::class, 'store'])->name('vouchers.store');
    Route::patch('voucher/massal/aktif', [VoucherController::class, 'bulkActive'])->name('vouchers.bulk-active');
    Route::delete('voucher/massal', [VoucherController::class, 'bulkDestroy'])->name('vouchers.bulk-destroy');
    Route::put('voucher/{voucher}', [VoucherController::class, 'update'])->whereNumber('voucher')->name('vouchers.update');
    Route::patch('voucher/{voucher}/aktif', [VoucherController::class, 'toggle'])->whereNumber('voucher')->name('vouchers.toggle');
    Route::delete('voucher/{voucher}', [VoucherController::class, 'destroy'])->whereNumber('voucher')->name('vouchers.destroy');

    // User: semua akun di tabel users selain admin. Hapus bersifat permanen; untuk menghentikan akses tanpa menghapus, gunakan blokir.
    Route::redirect('pelanggan', '/admin/user'); // alamat lama
    Route::get('user', [UserController::class, 'index'])->name('users.index');
    Route::post('user', [UserController::class, 'store'])->name('users.store');
    Route::patch('user/massal/blokir', [UserController::class, 'bulkBlock'])->name('users.bulk-block');
    Route::delete('user/massal', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
    Route::put('user/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
    Route::delete('user/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');
    Route::patch('user/{user}/blokir', [UserController::class, 'toggleBlock'])->whereNumber('user')->name('users.block');

    // Stok akun: satu tabel, dibagi per produk lewat slug (mis. /admin/akun/netflix-premium).
    Route::get('akun', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('akun', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('akun/massal/status', [AccountController::class, 'bulkStatus'])->name('accounts.bulk-status');
    Route::delete('akun/massal', [AccountController::class, 'bulkDestroy'])->name('accounts.bulk-destroy');
    Route::put('akun/{account}', [AccountController::class, 'update'])->whereNumber('account')->name('accounts.update');
    Route::delete('akun/{account}', [AccountController::class, 'destroy'])->whereNumber('account')->name('accounts.destroy');
    Route::get('akun/{product:slug}', [AccountController::class, 'index'])->name('accounts.product');

    // Backup database: buat sekarang, atur otomatis, unduh, hapus. File di storage/app/private/backups.
    Route::get('backup', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backup', [BackupController::class, 'store'])->name('backups.store');
    Route::put('backup/pengaturan', [BackupController::class, 'updateSettings'])->name('backups.settings');
    Route::get('backup/{file}/unduh', [BackupController::class, 'download'])->where('file', 'backup-[a-z]+-[0-9-]+\.sql\.gz')->name('backups.download');
    Route::delete('backup/{file}', [BackupController::class, 'destroy'])->where('file', 'backup-[a-z]+-[0-9-]+\.sql\.gz')->name('backups.destroy');

    // Log Aktivitas: siapa melakukan apa (ditulis middleware RecordActivity dan App\Support\ActivityEvents).
    Route::get('log-aktivitas', [ActivityLogController::class, 'index'])->name('logs.activity');

    // Log Sistem: file log Laravel per tanggal (baca, unduh, hapus). Nama file dibatasi pola laravel[-YYYY-MM-DD].log.
    Route::get('log-sistem', [SystemLogController::class, 'index'])->name('logs.system');
    Route::get('log-sistem/{file}/entri/{offset}', [SystemLogController::class, 'show'])->where('file', SystemLog::FILE_PATTERN)->whereNumber('offset')->name('logs.system.show');
    Route::get('log-sistem/{file}/unduh', [SystemLogController::class, 'download'])->where('file', SystemLog::FILE_PATTERN)->name('logs.system.download');
    Route::delete('log-sistem/{file}', [SystemLogController::class, 'destroy'])->where('file', SystemLog::FILE_PATTERN)->name('logs.system.destroy');
});
