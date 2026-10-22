<?php

use App\Http\Controllers\Store\Auth\GoogleController;
use App\Http\Controllers\Store\Auth\LoginController;
use App\Http\Controllers\Store\Auth\PasswordResetController;
use App\Http\Controllers\Store\Auth\RegisterController;
use App\Http\Controllers\Store\ChatController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\FaqController;
use App\Http\Controllers\Store\LegalController;
use App\Http\Controllers\Store\HomeController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\ProfileController;
use App\Http\Controllers\Store\ReportController;
use App\Http\Controllers\Store\ReviewController;
use App\Http\Controllers\Store\SeoController;
use App\Http\Controllers\Store\TopCustomerController;
use App\Http\Controllers\Store\VoucherController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/produk', [ProductController::class, 'index'])->name('products.index');

// Widget Tanya AI (tamu dan user login). Batas harian di menu AI > Perilaku; pengaman per menit di limiter `ai-chat`.
Route::get('/chat/riwayat', [ChatController::class, 'history'])->middleware('throttle:60,1')->name('chat.history');
Route::post('/chat', [ChatController::class, 'send'])->middleware('throttle:ai-chat')->name('chat.send');
Route::get('/produk/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::post('/produk/{slug}/voucher', [ProductController::class, 'checkVoucher'])->middleware('throttle:60,1')->name('products.voucher');
Route::get('/faq', FaqController::class)->name('faq');
Route::get('/{slug}', LegalController::class)->whereIn('slug', array_column(config('legal.pages'), 'slug'))->name('legal.show');
Route::get('/voucher', [VoucherController::class, 'index'])->name('vouchers.index');
Route::get('/top-pelanggan', TopCustomerController::class)->name('top-customers');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/lupa-sandi', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/lupa-sandi/kode', [PasswordResetController::class, 'send'])->middleware(PasswordResetController::sendThrottleEnabled() ? ['throttle:10,1'] : [])->name('password.send');
    Route::post('/lupa-sandi/verifikasi', [PasswordResetController::class, 'verify'])->middleware('throttle:20,1')->name('password.verify');
    Route::post('/lupa-sandi/password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');

    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/profil', [ProfileController::class, 'show'])->name('profil');
    Route::patch('/profil', [ProfileController::class, 'update'])->middleware('throttle:20,1')->name('profil.update');
    Route::patch('/profil/sandi', [ProfileController::class, 'password'])->middleware('throttle:10,1')->name('profil.password');
    Route::delete('/profil', [ProfileController::class, 'destroy'])->middleware('throttle:5,1')->name('profil.destroy');
    Route::post('/pesanan/{order}/ulasan', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('reviews.store');
    Route::get('/lapor-masalah', [ReportController::class, 'create'])->name('reports.create');
    Route::post('/laporan', [ReportController::class, 'store'])->middleware('throttle:10,1')->name('reports.store');

    // Checkout QRIS manual: GET dipakai tamu untuk kembali ke halaman produk setelah login.
    Route::get('/produk/{slug}/beli', [CheckoutController::class, 'resume'])->name('checkout.resume');
    Route::post('/produk/{slug}/beli', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/pesanan/{order}/status', [OrderController::class, 'status'])->middleware('throttle:60,1')->name('orders.status');
    Route::post('/pesanan/{order}/bayar', [OrderController::class, 'claim'])->middleware('throttle:10,1')->name('orders.claim');
    Route::post('/pesanan/{order}/batal', [OrderController::class, 'cancel'])->middleware('throttle:10,1')->name('orders.cancel');

    // GET juga diterima agar tamu yang menekan Klaim kembali ke sini setelah login (intended URL) dan klaimnya langsung dijalankan.
    Route::match(['get', 'post'], '/voucher/{voucher}/klaim', [VoucherController::class, 'claim'])->middleware('throttle:30,1')->whereNumber('voucher')->name('vouchers.claim');
});
