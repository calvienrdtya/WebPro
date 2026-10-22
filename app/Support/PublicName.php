<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Nama yang aman ditampilkan publik: nama depan dan inisial nama belakang ("Rizky Anwar" menjadi "Rizky A."). */
class PublicName
{
    public static function of(?string $name, string $fallback = 'Pelanggan'): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [$fallback];
        $first = Str::limit($parts[0], 18, '');

        return count($parts) > 1 ? $first.' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $first;
    }
}
