<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email notifikasi untuk admin, sesuai saklar di Pengaturan > Notifikasi: saklar utama (bawaan mati, tanpa alamat
 * bawaan) dan saklar per jenis (Setting::NOTIFY_TYPES, bawaan nyala).
 */
class AdminNotifier
{
    /** Alamat tujuan, atau null bila notifikasi mati atau alamat belum diisi. */
    public function target(): ?string
    {
        if (! Setting::bool(Setting::NOTIFY_EMAIL_ENABLED)) {
            return null;
        }

        $to = trim((string) Setting::get(Setting::NOTIFY_EMAIL_TO));

        return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : null;
    }

    public static function typeEnabled(string $type): bool
    {
        return Setting::bool('notify.email.type.'.$type, true);
    }

    /**
     * Memasukkan email ke antrean (App\Mail\QueuedMail; SMTP gagal dicoba ulang oleh antrean). Galat tidak
     * menggagalkan proses asal. Mengembalikan true bila dijadwalkan. $type = salah satu kunci Setting::NOTIFY_TYPES.
     */
    public function send(Mailable $mail, string $type): bool
    {
        $to = $this->target();

        if ($to === null || ! static::typeEnabled($type)) {
            return false;
        }

        try {
            Mail::to($to)->queue($mail);
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }
}
