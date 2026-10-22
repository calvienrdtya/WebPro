<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\UploadedImage;
use App\Support\ImageOptimizer;
use App\Support\StoreProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Optimalkan gambar yang sudah ada sebelum sistem ImageOptimizer: gambar produk, logo, hero, gambar bagikan, dan logo
 * kampus (unggahan maupun bawaan public/assets). File asli disimpan, jadi tiap gambar bisa dikembalikan dari admin.
 * Gambar yang sudah tercatat dilewati, jadi aman dijalankan ulang.
 */
#[AsCommand(name: 'images:optimize', description: 'Kecilkan dan simpan WebP gambar toko yang sudah ada (file asli disimpan, bisa dikembalikan per gambar)')]
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--dry-run : Hanya tampilkan daftar, tanpa mengubah apa pun}';

    private int $before = 0;

    private int $after = 0;

    public function handle(ImageOptimizer $images): int
    {
        $dry = (bool) $this->option('dry-run');
        $tracked = fn (?string $path) => $path && UploadedImage::where('path', $path)->exists();

        foreach (Product::withTrashed()->whereNotNull('image')->orderBy('id')->get() as $product) {
            if ($tracked($product->image)) {
                continue;
            }
            $this->optimizeOne("Produk {$product->name}", $product->image, true, $dry, function () use ($images, $product) {
                $new = $images->adopt($product->image, 'products', 'product');
                $new && $product->forceFill(['image' => $new])->saveQuietly();

                return $new;
            });
        }

        foreach (['logo' => 'logo', 'hero_image' => 'hero', 'og_image' => 'share'] as $key => $profile) {
            $uploaded = StoreProfile::uploaded($key);
            if ($tracked($uploaded)) {
                continue;
            }
            $source = $uploaded ?? config('store.'.StoreProfile::IMAGES[$key]);
            $this->optimizeOne("Toko {$key}".($uploaded ? '' : ' (bawaan)'), $source, (bool) $uploaded, $dry, function () use ($images, $key, $profile, $uploaded, $source) {
                $new = $images->adopt($source, 'store', $profile, fromUploads: (bool) $uploaded);
                $new && StoreProfile::putImage($key, $new, deleteOld: false);

                return $new;
            });
        }

        $campuses = StoreProfile::campuses();
        foreach ($campuses as $i => $campus) {
            if ($campus['uploaded'] && $tracked($campus['path'])) {
                continue;
            }
            $this->optimizeOne("Kampus {$campus['name']}", $campus['path'], $campus['uploaded'], $dry, function () use ($images, &$campuses, $i, $campus) {
                $new = $images->adopt($campus['path'], 'store/campus', 'campus', fromUploads: $campus['uploaded']);
                $new && $campuses[$i] = ['path' => $new, 'name' => $campus['name'], 'uploaded' => true];

                return $new;
            });
        }
        if (! $dry) {
            StoreProfile::putCampuses($campuses);
        }

        $this->newLine();
        $this->info(($dry ? 'Perkiraan awal: ' : 'Total: ').Number::fileSize($this->before, 1).($dry ? '' : ' menjadi '.Number::fileSize($this->after, 1)).'.');

        return self::SUCCESS;
    }

    private function optimizeOne(string $label, string $path, bool $uploaded, bool $dry, callable $optimize): void
    {
        $disk = Storage::disk('uploads');
        $size = $uploaded ? ($disk->exists($path) ? $disk->size($path) : 0) : (is_file(public_path($path)) ? filesize(public_path($path)) : 0);
        $this->before += $size;
        if ($dry || ! $size) {
            $this->line(sprintf('  %-40s %10s%s', $label, Number::fileSize($size, 1), $size ? '' : '  (file tidak ada, dilewati)'));

            return;
        }

        $new = $optimize();
        $after = $new ? $disk->size($new) : $size;
        $this->after += $after;
        $this->line(sprintf('  %-40s %10s -> %s', $label, Number::fileSize($size, 1), Number::fileSize($after, 1)));
    }
}
