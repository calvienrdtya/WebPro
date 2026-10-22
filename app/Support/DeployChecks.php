<?php

namespace App\Support;

use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cek kesiapan production untuk menu Panduan Deploy. Membaca kondisi server tempat aplikasi berjalan; tidak pernah
 * menampilkan isi rahasia (API key, sandi), hanya status. status: ok, warn (sebaiknya dibereskan), fail (wajib).
 */
class DeployChecks
{
    /** @return list<array{group: string, label: string, status: string, detail: string}> */
    public static function all(): array
    {
        $checks = [];
        $add = function (string $group, string $label, string $status, string $detail) use (&$checks) {
            $checks[] = compact('group', 'label', 'status', 'detail');
        };

        // Konfigurasi aplikasi
        $add('Aplikasi', 'APP_ENV = production', app()->environment('production') ? 'ok' : 'fail', 'Sekarang: '.app()->environment().'.');
        $add('Aplikasi', 'APP_DEBUG mati', config('app.debug') ? 'fail' : 'ok', config('app.debug') ? 'Halaman error membocorkan isi konfigurasi. Set APP_DEBUG=false.' : 'Halaman error aman.');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $add('Aplikasi', 'APP_URL memakai https', $https ? 'ok' : 'fail', 'Sekarang: '.config('app.url'));
        $add('Aplikasi', 'APP_KEY terisi', config('app.key') ? 'ok' : 'fail', 'Cadangkan nilainya: API key AI dienkripsi dengan kunci ini.');
        $add('Aplikasi', 'Cookie sesi hanya lewat https', config('session.secure') ? 'ok' : 'warn', 'Set SESSION_SECURE_COOKIE=true setelah https aktif.');
        $throttle = (bool) config('proakses.password_reset_throttle');
        $add('Aplikasi', 'Batas kirim kode lupa sandi aktif', $throttle ? 'ok' : 'fail', $throttle ? 'Aktif.' : 'Set PASSWORD_RESET_THROTTLE=true.');
        $add('Aplikasi', 'Cache konfigurasi', app()->configurationIsCached() ? 'ok' : 'warn', 'Jalankan php artisan config:cache (ulangi setiap .env berubah).');

        // Log Sistem
        $channels = config('logging.default') === 'stack' ? (array) config('logging.channels.stack.channels') : [config('logging.default')];
        $daily = in_array('daily', $channels, true);
        $add('Log', 'Log harian', $daily ? 'ok' : 'fail', $daily ? 'Satu file per hari, dihapus sesuai masa simpan di Pengaturan.' : 'Set LOG_STACK=daily agar menu Log Sistem dan penghapusan otomatis berfungsi.');
        $level = (string) config('logging.channels.daily.level');
        $add('Log', 'Level log production', $level === 'debug' ? 'warn' : 'ok', $level === 'debug' ? 'LOG_LEVEL=debug membuat file log cepat besar. Disarankan LOG_LEVEL=warning.' : "Level: {$level}.");

        // Cron dan antrean
        $beat = Cache::get('schedule.heartbeat');
        $alive = $beat && Carbon::parse($beat)->gt(now()->subMinutes(3));
        $add('Cron dan antrean', 'Cron schedule:run berjalan', $alive ? 'ok' : 'fail', $beat ? 'Detak terakhir '.Carbon::parse($beat)->diffForHumans().'.' : 'Belum pernah ada detak. Pasang cron * * * * * php artisan schedule:run.');
        $failed = DB::table('failed_jobs')->count();
        $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(5)->getTimestamp())->count();
        $add('Cron dan antrean', 'Antrean tidak macet', $stuck > 0 ? 'fail' : ($failed > 0 ? 'warn' : 'ok'), "{$stuck} job menunggu lebih dari 5 menit, {$failed} job gagal.");

        // Backup database
        $last = app(DatabaseBackup::class)->lastAt();
        $fresh = $last && $last->gt(now()->subDays(Setting::backupIntervalDays() + 1));
        $add('Data', 'Backup database rutin', ! Setting::backupEnabled() ? 'warn' : ($fresh ? 'ok' : 'fail'),
            ! Setting::backupEnabled() ? 'Backup otomatis dimatikan di menu Backup.'
                : ($last ? 'Backup terakhir '.$last->diffForHumans().'.' : 'Belum pernah ada backup. Buat di menu Backup; otomatis berjalan lewat cron.')
                .' Unduh salinannya ke tempat lain secara berkala.');

        // Email
        $mailer = config('mail.default');
        $host = (string) config("mail.mailers.{$mailer}.host");
        $add('Email', 'Pengirim email sungguhan', in_array($mailer, ['log', 'array'], true) ? 'fail' : (str_contains($host, 'gmail') ? 'warn' : 'ok'),
            in_array($mailer, ['log', 'array'], true) ? "MAIL_MAILER={$mailer}: email tidak terkirim." : (str_contains($host, 'gmail') ? 'Masih SMTP Gmail. Pakai penyedia khusus (Resend/Brevo) dan atur SPF/DKIM agar tidak masuk spam.' : "Mailer: {$mailer}."));
        $from = (string) config('mail.from.address');
        $add('Email', 'Alamat pengirim', $from === '' || str_ends_with($from, '@example.com') ? 'fail' : 'ok', $from === '' ? 'MAIL_FROM_ADDRESS kosong.' : "Dari: {$from}.");

        // Data dan akun
        $defaultAdmin = User::where('email', 'admin@gmail.com')->exists();
        $add('Data', 'Akun admin bawaan sudah diganti', $defaultAdmin ? 'fail' : 'ok', $defaultAdmin ? 'Akun admin@gmail.com bersandi lemah masih ada.' : 'Tidak ada akun admin@gmail.com.');
        $samples = Review::whereNull('user_id')->count();
        $add('Data', 'Ulasan contoh sudah dihapus', $samples > 0 ? 'warn' : 'ok', $samples > 0 ? "{$samples} ulasan tanpa pembeli (data contoh seed) masih ada dan ikut dihitung rating beranda." : 'Tidak ada ulasan contoh.');
        $add('Data', 'Nomor WhatsApp admin', Whatsapp::number() ? 'ok' : 'fail', Whatsapp::number() ? 'ADMIN_WHATSAPP_NUMBER terisi.' : 'Isi ADMIN_WHATSAPP_NUMBER.');
        $qris = (bool) Setting::get(Setting::QRIS_IMAGE);
        $add('Data', 'Gambar QRIS asli', $qris ? 'ok' : 'warn', $qris ? 'QRIS unggahan sudah dipakai.' : 'Masih gambar bawaan. Unggah QRIS asli di menu Pengaturan.');
        $google = (bool) config('services.google.client_id');
        $add('Data', 'Login Google', $google ? 'ok' : 'warn', $google ? 'Pastikan alamat callback domain production terdaftar di Google Cloud.' : 'Isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET.');

        // Server
        $add('Server', 'Ekstensi GD', function_exists('imagepng') ? 'ok' : 'warn', 'Dipakai untuk membuat gambar Tes vision di menu AI > Koneksi.');
        $add('Server', 'GD mendukung WebP', ImageOptimizer::webpSupported() ? 'ok' : 'warn', ImageOptimizer::webpSupported()
            ? 'Gambar unggahan dikecilkan dan disimpan WebP.'
            : 'Gambar unggahan disimpan apa adanya (tidak dikecilkan), toko jadi lebih lambat. Minta hosting mengaktifkan GD dengan WebP.');
        $upload = static::bytes(ini_get('upload_max_filesize'));
        $post = static::bytes(ini_get('post_max_size'));
        $add('Server', 'Batas unggah PHP', min($upload, $post) >= 20 * 1024 * 1024 ? 'ok' : 'warn', 'upload_max_filesize '.ini_get('upload_max_filesize').', post_max_size '.ini_get('post_max_size').'. Disarankan 40M untuk lampiran pengetahuan AI.');
        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache'), 'public/uploads' => public_path('uploads')] as $name => $path) {
            $add('Server', "Folder {$name} bisa ditulis", is_dir($path) && is_writable($path) ? 'ok' : 'fail', $path);
        }

        // AI
        if (AiSettings::enabled()) {
            $add('AI', 'Router AI memakai https', str_starts_with(AiSettings::baseUrl(), 'https://') ? 'ok' : 'warn', 'API key AI terkirim tanpa enkripsi lewat http.');
        }

        return $checks;
    }

    private static function bytes(string|false $value): int
    {
        $value = trim((string) $value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
