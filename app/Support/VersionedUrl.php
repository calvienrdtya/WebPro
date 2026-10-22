<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * URL file publik dengan penanda versi `?v=<waktu ubah file>`. Browser boleh menyimpan gambar lama-lama (cache di
 * public/.htaccess), dan begitu file diganti (mis. gambar dikecilkan manual dengan nama yang sama) URL-nya ikut
 * berubah sehingga pengunjung langsung mendapat versi baru.
 */
class VersionedUrl
{
    /** File di disk `uploads` (public/uploads). */
    public static function upload(string $path): string
    {
        $disk = Storage::disk('uploads');

        return static::withVersion($disk->url($path), rescue(fn () => $disk->path($path), null, false));
    }

    /** File di folder public (mis. assets/hero-section.png). */
    public static function asset(string $path): string
    {
        return static::withVersion(asset($path), public_path($path));
    }

    /**
     * Atribut `width="…" height="…"` dari ukuran asli file, agar browser menyiapkan ruang sebelum gambar termuat
     * (Lighthouse "unsized-images"). Kosong bila ukuran tidak terbaca (mis. SVG).
     */
    public static function sizeAttributes(string $path, bool $upload): string
    {
        $file = $upload ? rescue(fn () => Storage::disk('uploads')->path($path), null, false) : public_path($path);
        $size = $file && is_file($file) ? @getimagesize($file) : false;

        return $size ? 'width="'.$size[0].'" height="'.$size[1].'"' : '';
    }

    private static function withVersion(string $url, ?string $file): string
    {
        $time = $file && is_file($file) ? filemtime($file) : false;

        return $time ? $url.(str_contains($url, '?') ? '&' : '?').'v='.$time : $url;
    }
}
