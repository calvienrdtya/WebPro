<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\UploadedImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gambar unggahan admin: file ASLI disimpan apa adanya (disk local, storage/app/private/image-originals), lalu toko
 * memakai versi TAMPIL yang dikecilkan ke ukuran maksimal per jenis (config proakses.images.profiles) dan disimpan WebP
 * dengan kualitas dari Pengaturan (bawaan 90). Per gambar admin bisa kembali ke file asli atau mengoptimalkan lagi.
 *
 * Pengaman: hasil yang tidak jelas lebih kecil dari aslinya (config min_saving) tidak dipakai; gambar sangat besar,
 * format yang tidak terbaca GD, atau server tanpa dukungan WebP membuat file asli dipakai apa adanya (tanpa galat).
 */
class ImageOptimizer
{
    private const ORIGINALS = 'image-originals';

    /** Simpan unggahan baru; mengembalikan path file tampil di disk uploads. */
    public function store(UploadedFile $file, string $dir, string $profile): string
    {
        return $this->ingest($file->getRealPath(), $file->getClientOriginalName(), $file->getClientOriginalExtension(), $dir, $profile);
    }

    /**
     * Optimalkan file yang sudah ada (unggahan lama di disk uploads, atau gambar bawaan di folder public). File
     * unggahan lama dipindah menjadi file asli; gambar bawaan hanya disalin (tetap ada di public/assets).
     */
    public function adopt(string $path, string $dir, string $profile, bool $fromUploads = true): ?string
    {
        $absolute = $fromUploads ? Storage::disk('uploads')->path($path) : public_path($path);
        if (! is_file($absolute)) {
            return null;
        }
        $new = $this->ingest($absolute, basename($path), pathinfo($path, PATHINFO_EXTENSION), $dir, $profile);
        if ($fromUploads && $new !== $path) {
            Storage::disk('uploads')->delete($path);
        }

        return $new;
    }

    /** Data untuk admin: ukuran asli dan tampil, dan tombol apa yang tersedia. */
    public function info(?string $path): ?array
    {
        if (! $path) {
            return null;
        }
        $row = UploadedImage::where('path', $path)->first();
        if (! $row) {
            $file = Storage::disk('uploads')->path($path);

            return is_file($file) ? ['tracked' => false, 'optimized' => false, 'size' => filesize($file), 'can_original' => false, 'can_optimize' => true] : null;
        }

        return [
            'tracked' => true,
            'optimized' => $row->optimized,
            'size' => $row->size,
            'width' => $row->width,
            'height' => $row->height,
            'format' => strtoupper(pathinfo($row->path, PATHINFO_EXTENSION)),
            'quality' => $row->quality,
            'original_size' => $row->original_size,
            'original_width' => $row->original_width,
            'original_height' => $row->original_height,
            'can_original' => $row->optimized,
            'can_optimize' => ! $row->optimized,
        ];
    }

    /** Pakai file asli untuk gambar ini. Mengembalikan path tampil yang baru. */
    public function useOriginal(string $path): string
    {
        $row = UploadedImage::where('path', $path)->firstOrFail();
        $ext = strtolower(pathinfo($row->original_path, PATHINFO_EXTENSION));
        $new = $this->newPath(dirname($row->path), $ext);
        Storage::disk('uploads')->put($new, Storage::disk('local')->get($row->original_path));
        Storage::disk('uploads')->delete($row->path);

        $row->update([
            'path' => $new, 'optimized' => false, 'quality' => null,
            'size' => $row->original_size, 'width' => $row->original_width, 'height' => $row->original_height,
        ]);

        return $new;
    }

    /** Optimalkan lagi dari file asli (kualitas saat ini), atau adopsi unggahan lama yang belum tercatat. */
    public function optimize(string $path, string $profile): string
    {
        $row = UploadedImage::where('path', $path)->first();
        if (! $row) {
            return $this->adopt($path, dirname($path), $profile) ?? $path;
        }

        $result = $this->process(Storage::disk('local')->path($row->original_path), $row->profile, $row->original_size);
        if ($result === null) {
            return $path;
        }
        $new = $this->newPath(dirname($row->path), $result['ext']);
        Storage::disk('uploads')->put($new, $result['data']);
        Storage::disk('uploads')->delete($row->path);
        $row->update(['path' => $new, 'optimized' => true, 'quality' => $result['quality'],
            'size' => strlen($result['data']), 'width' => $result['width'], 'height' => $result['height']]);
        if (! $row->share_path) {
            $row->update(['share_path' => $this->shareVariant(Storage::disk('local')->path($row->original_path), $row->profile, dirname($new))]);
        }

        return $new;
    }

    /** Hapus file tampil, varian, file asli, dan catatannya. Unggahan lama tanpa catatan cukup dihapus filenya. */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }
        $row = UploadedImage::where('path', $path)->first();
        Storage::disk('uploads')->delete($path);
        if ($row) {
            if ($row->share_path) {
                Storage::disk('uploads')->delete($row->share_path);
            }
            Storage::disk('local')->delete($row->original_path);
            $row->delete();
        }
    }

    /** URL untuk pratinjau tautan (og:image): varian JPG bila ada, selain itu file tampil. */
    public function shareUrl(string $path): string
    {
        $share = UploadedImage::where('path', $path)->value('share_path');

        return VersionedUrl::upload($share && Storage::disk('uploads')->exists($share) ? $share : $path);
    }

    public static function webpSupported(): bool
    {
        return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
    }

    private function ingest(string $source, ?string $name, ?string $ext, string $dir, string $profile): string
    {
        $ext = strtolower($ext ?: pathinfo($source, PATHINFO_EXTENSION) ?: 'bin');
        $original = self::ORIGINALS.'/'.$dir.'/'.Str::random(40).'.'.$ext;
        Storage::disk('local')->put($original, file_get_contents($source));
        $originalSize = filesize($source);
        [$ow, $oh] = @getimagesize($source) ?: [0, 0];

        $result = $this->process($source, $profile, $originalSize);
        if ($result !== null) {
            $path = $this->newPath($dir, $result['ext']);
            Storage::disk('uploads')->put($path, $result['data']);
        } else {
            $path = $this->newPath($dir, $ext);
            Storage::disk('uploads')->put($path, file_get_contents($source));
        }

        UploadedImage::create([
            'path' => $path,
            'profile' => $profile,
            'original_path' => $original,
            'original_name' => $name ? Str::limit($name, 250, '') : null,
            'original_size' => $originalSize,
            'original_width' => $ow,
            'original_height' => $oh,
            'size' => $result ? strlen($result['data']) : $originalSize,
            'width' => $result['width'] ?? $ow,
            'height' => $result['height'] ?? $oh,
            'optimized' => $result !== null,
            'quality' => $result['quality'] ?? null,
            'share_path' => $result ? $this->shareVariant($source, $profile, $dir) : null,
        ]);

        return $path;
    }

    /**
     * Kecilkan dan simpan ulang. Null bila sebaiknya memakai file asli (tidak terbaca, terlalu besar, tanpa WebP,
     * atau hasilnya tidak lebih kecil dari aslinya).
     *
     * @return array{data: string, ext: string, width: int, height: int, quality: int}|null
     */
    private function process(string $source, string $profile, int $originalSize, ?array $box = null, ?string $format = null, ?int $quality = null): ?array
    {
        $p = config("proakses.images.profiles.{$profile}");
        $box ??= ['width' => $p['width'], 'height' => $p['height']];
        $format ??= $p['format'];
        $quality ??= Setting::imageQuality();

        try {
            $info = @getimagesize($source);
            if (! $info || (int) config('proakses.images.max_pixels') < $info[0] * $info[1] || ($format === 'webp' && ! self::webpSupported())) {
                return null;
            }
            if (! $this->ensureMemory($info[0] * $info[1], filesize($source))) {
                Log::warning('Gambar terlalu besar untuk memori PHP, file asli dipakai.', ['file' => basename($source), 'pixels' => $info[0] * $info[1]]);

                return null;
            }
            $image = @imagecreatefromstring(file_get_contents($source));
            if (! $image instanceof GdImage) {
                return null;
            }
            $image = $this->orient($image, $source, $info[2]);
            [$w, $h] = [imagesx($image), imagesy($image)];

            $scale = min(1, $box['width'] ? $box['width'] / $w : 1, $box['height'] ? $box['height'] / $h : 1);
            [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

            $canvas = imagecreatetruecolor($nw, $nh);
            if ($format === 'jpg') {
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // JPG tanpa transparansi: latar putih
                imagealphablending($canvas, true);
            } else {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            }
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
            unset($image); // bebaskan salinan besar sebelum menyimpan

            ob_start();
            if ($format === 'jpg') {
                imageinterlace($canvas, true);
                imagejpeg($canvas, null, $quality);
            } else {
                imagewebp($canvas, null, $quality);
            }
            $data = (string) ob_get_clean();

            // Hanya dipakai bila jelas lebih kecil dari aslinya (config min_saving). Penghematan tipis tidak sebanding
            // dengan kompresi ulang (mis. file yang sudah WebP dari Squoosh), jadi file asli yang dipakai.
            if ($data === '' || strlen($data) > $originalSize * (1 - (float) config('proakses.images.min_saving'))) {
                return null;
            }

            return ['data' => $data, 'ext' => $format, 'width' => $nw, 'height' => $nh, 'quality' => $quality];
        } catch (Throwable $e) {
            Log::warning('Gambar tidak bisa dioptimalkan, file asli dipakai.', ['file' => basename($source), 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * GD memakai sekitar 4-5 byte per piksel untuk tiap salinan gambar (asli, hasil putar, kanvas). Bila sisa memori PHP
     * kurang, batasnya dinaikkan untuk proses ini saja; bila hosting tidak mengizinkan, gambar tidak diproses.
     */
    private function ensureMemory(int $pixels, int $bytes): bool
    {
        $limit = $this->bytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return true; // tanpa batas
        }
        $needed = memory_get_usage(true) + $pixels * 5 * 2 + $bytes * 2 + 16 * 1024 * 1024;
        if ($needed <= $limit) {
            return true;
        }

        return @ini_set('memory_limit', (string) $needed) !== false && $this->bytes((string) ini_get('memory_limit')) >= $needed;
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** JPG kecil untuk pratinjau tautan (gambar produk dan hero), agar terbaca semua aplikasi. */
    private function shareVariant(string $source, string $profile, string $dir): ?string
    {
        if (! (config("proakses.images.profiles.{$profile}.share") ?? false)) {
            return null;
        }
        $v = config('proakses.images.share_variant');
        $result = $this->process($source, $profile, PHP_INT_MAX, ['width' => $v['width'], 'height' => $v['height']], 'jpg', $v['quality']);
        if ($result === null) {
            return null;
        }
        $path = $this->newPath($dir.'/share', 'jpg');
        Storage::disk('uploads')->put($path, $result['data']);

        return $path;
    }

    /** Foto dari HP menyimpan arah putar di EXIF; GD mengabaikannya, jadi diputar di sini. */
    private function orient(GdImage $image, string $source, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }
        $angle = match ((int) (@exif_read_data($source)['Orientation'] ?? 1)) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }

    private function newPath(string $dir, string $ext): string
    {
        return trim($dir, '/').'/'.Str::random(40).'.'.$ext;
    }
}
