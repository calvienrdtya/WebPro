<?php

namespace App\Providers;

use App\Support\ActivityEvents;
use App\Support\AiSettings;
use App\Support\StoreProfile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mime\Address;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @rupiah($angka) -> "Rp 45.000"
        Blade::directive('rupiah', fn (string $expression) => "<?php echo 'Rp '.number_format((int) ({$expression}), 0, ',', '.'); ?>");

        // Chat Tanya AI di toko: pengaman spam per menit (angka di menu AI > Koneksi > Pengaturan lanjutan).
        RateLimiter::for('ai-chat', fn (Request $request) => Limit::perMinute(AiSettings::advanced('chat_per_minute'))
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn () => response()->json(['limited' => true, 'reply' => 'Terlalu banyak pesan dalam satu menit. Tunggu sebentar lalu coba lagi.'], 429)));

        // Log Aktivitas: perubahan model, login/keluar, dan perintah terjadwal (aksi rute dicatat middleware RecordActivity).
        ActivityEvents::register();

        // Nama pengirim email = nama toko (Tampilan Toko > Identitas), bukan MAIL_FROM_NAME/APP_NAME. Alamat tetap dari .env.
        Event::listen(MessageSending::class, function (MessageSending $event) {
            foreach ($event->message->getFrom() as $from) {
                if ($from->getAddress() === config('mail.from.address')) {
                    $event->message->from(new Address($from->getAddress(), StoreProfile::name()));
                }
            }
        });
    }
}
