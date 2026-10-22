<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Akun tersedia yang sudah lewat masa aktifnya tidak boleh terkirim ke pelanggan.
// Di hosting perlu cron: * * * * * cd /path/ke/backend && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('accounts:expire')->dailyAt('00:05')->withoutOverlapping();

// Pesanan yang batas bayarnya lewat dikedaluwarsakan dan stok/kuota voucher dilepas (halaman pesanan juga melakukannya saat dibuka).
Schedule::command('orders:expire')->everyMinute()->withoutOverlapping();

// Akun bulan ke-2 dst dikirim otomatis saat jatuh tempo; bila stok kurang, admin diberi tahu (hanya sekali per 24 jam per kiriman).
Schedule::command('orders:deliver-due')->hourly()->withoutOverlapping();

// Pengingat perpanjangan ke pelanggan (saklar dan jumlah hari di Pengaturan).
Schedule::command('orders:remind-renewal')->dailyAt('09:00')->withoutOverlapping();

// Gambar dari pelanggan di chat AI dihapus setelah masa simpan (Pengaturan lanjutan AI, bawaan 30 hari).
Schedule::command('ai:prune-chat-images')->dailyAt('03:00')->withoutOverlapping();

// Backup database otomatis: diperiksa tiap hari, dibuat bila menyala dan selang hari di menu Backup sudah lewat.
Schedule::command('backup:database --auto')->dailyAt(config('proakses.backup.time'))->withoutOverlapping();

// Log Sistem (file harian) dan Log Aktivitas yang melewati masa simpan di Pengaturan dihapus.
Schedule::command('logs:prune')->dailyAt('03:15')->withoutOverlapping();

// Antrean database (mis. merapikan entri pengetahuan AI). Tanpa worker terpisah: dijalankan tiap menit oleh cron yang sama
// dan berhenti sendiri saat antrean kosong. --timeout cukup untuk konversi PDF besar (lihat convert_timeout di menu AI).
Schedule::command('queue:work --stop-when-empty --max-time=55 --timeout=700')->everyMinute()->withoutOverlapping(15)->runInBackground();

// Detak scheduler: menu Panduan Deploy memakai ini untuk memastikan cron schedule:run benar-benar berjalan di server.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever('schedule.heartbeat', now()->toIso8601String()))->everyMinute()->name('heartbeat');
