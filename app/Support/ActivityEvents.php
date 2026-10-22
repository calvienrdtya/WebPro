<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;

/** Sumber Log Aktivitas selain rute dan perintah terjadwal: perubahan model serta login/keluar. */
class ActivityEvents
{
    public static function register(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", fn (string $name, array $data) => Activity::capture($event, $data[0] ?? null));
        }

        Event::listen(Login::class, function (Login $e) {
            if (! $e->user instanceof User) {
                return;
            }
            [$action, $text] = match (true) {
                request()->routeIs('auth.google.callback') => ['auth.login.google', 'Masuk dengan Google'],
                request()->is('register') => ['auth.register', 'Mendaftar akun baru'],
                default => ['auth.login', 'Masuk'],
            };
            Activity::record($action, 'auth', $text, ['actor' => $e->user, 'subject' => $e->user]);
        });

        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user instanceof User) {
                Activity::record('auth.logout', 'auth', 'Keluar', ['actor' => $e->user, 'subject' => $e->user]);
            }
        });

        // Gagal masuk: sandi tidak pernah dicatat, hanya email yang dicoba.
        Event::listen(Failed::class, function (Failed $e) {
            $email = (string) ($e->credentials['email'] ?? '');
            Activity::record('auth.failed', 'auth', $e->user ? 'Gagal masuk: sandi salah' : 'Gagal masuk: email tidak terdaftar', [
                'actor_type' => 'guest',
                'actor_email' => $email !== '' ? $email : null,
                'subject' => $e->user instanceof User ? $e->user : null,
            ]);
        });
    }
}
