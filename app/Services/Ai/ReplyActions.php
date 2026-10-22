<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AiSettings;
use App\Support\Whatsapp;

/**
 * Tombol aksi di bawah jawaban AI. Saat ini: "Chat WhatsApp" bila jawaban mengarahkan ke admin, yaitu memuat salah
 * satu kata pemicu (menu Koneksi > Pengaturan lanjutan) atau nomor WhatsApp admin dalam bentuk apa pun (+62, 62, 08).
 */
class ReplyActions
{
    /**
     * Tombol tautan di bawah jawaban: Login bila tamu diarahkan ke halaman login, Lapor Masalah bila diarahkan ke sana.
     *
     * @return list<array{t: string, href: string}>
     */
    public static function links(string $reply, ?User $user): array
    {
        $links = [];

        if ($user === null && str_contains($reply, route('login'))) {
            $links[] = ['t' => 'Login', 'href' => route('login')];
        }

        if (str_contains($reply, route('reports.create'))) {
            $links[] = ['t' => 'Lapor Masalah', 'href' => route('reports.create')];
        }

        return $links;
    }

    public static function wantsWhatsapp(string $reply): bool
    {
        $text = mb_strtolower($reply);

        foreach (AiSettings::whatsappTriggers() as $trigger) {
            if ($trigger !== '' && str_contains($text, mb_strtolower($trigger))) {
                return true;
            }
        }

        $number = Whatsapp::number();
        if ($number === null) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $reply);
        $local = '0'.substr($number, 2); // 6281... -> 081...

        return str_contains($digits, $number) || str_contains($digits, $local);
    }
}
