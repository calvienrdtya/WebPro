<?php

namespace App\Services\Ai;

use App\Support\AiSettings;

/**
 * Membersihkan balasan model sebelum ditampilkan: membuang baris yang bocor dari instruksi tersembunyi penyedia
 * (mis. "skipped: ..., add when ..." dari Antigravity) beserta garis pemisah yang tersisa di ujung.
 * Awalan yang dibuang diatur di menu Koneksi > Pengaturan lanjutan.
 */
class ReplyCleaner
{
    /** @param  list<string>|null  $prefixes */
    public static function clean(string $text, ?array $prefixes = null): string
    {
        $prefixes = array_map('mb_strtolower', $prefixes ?? AiSettings::stripPrefixes());

        $lines = array_filter(preg_split('/\R/u', $text), function (string $line) use ($prefixes) {
            // Abaikan penanda daftar/kutipan/tebal di depan baris, mis. "* skipped:" atau "> **skipped:**".
            $bare = mb_strtolower(ltrim($line, " \t-*>_`#"));

            foreach ($prefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($bare, $prefix)) {
                    return false;
                }
            }

            return true;
        });

        $text = trim(implode("\n", $lines));
        $text = preg_replace('/(\R\s*(-{3,}|\*{3,}|_{3,})\s*)+$/u', '', $text); // garis pemisah yang tertinggal di akhir

        return trim(preg_replace('/(\R\s*){3,}/u', "\n\n", $text));
    }
}
