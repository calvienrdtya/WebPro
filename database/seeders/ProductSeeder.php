<?php

namespace Database\Seeders;

use App\Enums\PriceMode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Katalog awal dari frontend. Memakai firstOrCreate: produk yang sudah ada (mis. sudah diedit admin) tidak ditimpa.
 */
class ProductSeeder extends Seeder
{
    /** Pengali harga per durasi pada frontend (1 / 2 / 3 bulan). */
    private const MULTIPLIERS = [1 => 1.0, 2 => 1.9, 3 => 2.8];

    public function run(): void
    {
        // Templat durasi global; harga per produk dipasang di bawah.
        $variants = collect(array_keys(self::MULTIPLIERS))->mapWithKeys(fn (int $months) => [
            $months => Variant::firstOrCreate(['months' => $months], ['title' => "{$months} Bulan", 'is_active' => true]),
        ]);

        foreach ($this->catalog() as $order => $item) {
            $product = Product::firstOrNew(['slug' => $item['slug']]);

            if ($product->exists) {
                continue;
            }

            $product->fill([
                'name' => $item['name'],
                'category_id' => $this->category($item['category'])->id,
                'account_type' => $item['account_type'],
                'description' => $item['description'],
                'highlights' => $item['highlights'],
                'specs' => array_map(fn ($v, $k) => ['label' => $k, 'value' => $v], $item['specs'], array_keys($item['specs'])),
                'price' => $item['compare'],
                'discount_percent' => (int) round(($item['compare'] - $item['prices'][0]) / $item['compare'] * 100),
                'image' => $this->storeImage($item['slug'], $item['image']),
                'sort_order' => $order + 1,
                'is_active' => true,
            ])->save();

            foreach (self::MULTIPLIERS as $months => $multiplier) {
                if ($months === 1) {
                    // 1 bulan mengikuti harga produk.
                    $product->productVariants()->create(['variant_id' => $variants[1]->id, 'price_mode' => PriceMode::FromProduct]);

                    continue;
                }

                // 2 dan 3 bulan: harga sendiri, lebih hemat per bulan daripada beli 1 bulan.
                $listPrice = (int) round($item['compare'] * $multiplier, -3);
                $price = $item['prices'][$months - 1];

                $product->productVariants()->create([
                    'variant_id' => $variants[$months]->id,
                    'price_mode' => PriceMode::Custom,
                    'price' => $listPrice,
                    'discount_percent' => (int) round(($listPrice - $price) / $listPrice * 100),
                ]);
            }
        }
    }

    private function category(string $slug): ProductCategory
    {
        $names = ['ai' => 'Akun AI', 'streaming' => 'Streaming', 'tools' => 'Tools'];

        return ProductCategory::firstOrCreate(['slug' => $slug], ['name' => $names[$slug] ?? ucfirst($slug)]);
    }

    private function storeImage(string $slug, string $file): string
    {
        $path = "products/{$slug}.".pathinfo($file, PATHINFO_EXTENSION);
        $disk = Storage::disk('uploads');

        if (! $disk->exists($path)) {
            $disk->put($path, file_get_contents(__DIR__.'/product-images/'.$file));
        }

        return $path;
    }

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        $devices = 'Smart TV, HP, tablet, laptop';

        return [
            [
                'slug' => 'chatgpt-plus', 'name' => 'ChatGPT Plus', 'category' => 'ai', 'account_type' => 'chatgpt',
                'image' => 'chatgpt-plus.png', 'prices' => [45000, 86000, 126000], 'compare' => 79000,
                'description' => 'Akses penuh model ChatGPT terbaru dengan respons lebih cepat, akses saat jam sibuk, serta fitur Vision, analisis data, dan pembuatan gambar.',
                'highlights' => ['Akses model terbaru tanpa batas antrean', 'Respons lebih cepat saat jam sibuk', 'Fitur Vision, analisis file, dan pembuatan gambar', 'Custom GPT dan penjelajahan web'],
                'specs' => ['Jenis akun' => 'Sharing terkelola', 'Region' => 'Global', 'Perangkat' => 'Web, iOS, Android'],
            ],
            [
                'slug' => 'claude-pro', 'name' => 'Claude Pro', 'category' => 'ai', 'account_type' => 'claude',
                'image' => 'claude-pro.png', 'prices' => [45000, 86000, 126000], 'compare' => 85000,
                'description' => 'Limit penggunaan jauh lebih besar dari versi gratis, akses model Claude paling mumpuni, dan prioritas saat trafik tinggi untuk kerja panjang.',
                'highlights' => ['Limit pesan hingga 5x lipat versi gratis', 'Akses model Claude paling mumpuni', 'Prioritas akses saat trafik tinggi', 'Fitur Projects untuk mengelola konteks'],
                'specs' => ['Jenis akun' => 'Sharing terkelola', 'Region' => 'Global', 'Perangkat' => 'Web, iOS, Android'],
            ],
            [
                'slug' => 'netflix-premium', 'name' => 'Netflix Premium', 'category' => 'streaming', 'account_type' => 'netflix',
                'image' => 'netflix-premium.png', 'prices' => [30000, 57000, 84000], 'compare' => 65000,
                'description' => 'Streaming 4K Ultra HD di TV, HP, tablet, dan laptop dengan satu profil pribadi.',
                'highlights' => ['Kualitas 4K Ultra HD + HDR', '1 profil pribadi anti-diganggu', 'Bisa diputar di semua perangkat'],
                'specs' => ['Jenis akun' => '1 profil di akun sharing', 'Kualitas' => '4K UHD', 'Perangkat' => $devices],
            ],
            [
                'slug' => 'disney-hotstar', 'name' => 'Disney+ Hotstar', 'category' => 'streaming', 'account_type' => 'disney',
                'image' => 'disney-hotstar.png', 'prices' => [20000, 38000, 56000], 'compare' => 45000,
                'description' => 'Film dan serial Disney, Marvel, Star Wars, Pixar, plus olahraga langsung hingga 4K.',
                'highlights' => ['Katalog Disney, Marvel, Star Wars, Pixar', 'Tayangan olahraga langsung', 'Kualitas hingga 4K', '1 profil pribadi'],
                'specs' => ['Jenis akun' => '1 profil di akun sharing', 'Kualitas' => 'Hingga 4K', 'Perangkat' => $devices],
            ],
            [
                'slug' => 'hbo-max', 'name' => 'HBO Max', 'category' => 'streaming', 'account_type' => 'hbo',
                'image' => 'hbo-max.png', 'prices' => [22000, 42000, 62000], 'compare' => 49000,
                'description' => 'Serial dan film original HBO Max, Warner Bros, dan DC, Full HD hingga 4K pada satu profil pribadi.',
                'highlights' => ['Serial original HBO dan film Warner Bros', 'Full HD hingga 4K', '1 profil pribadi', 'Bisa diputar di semua perangkat'],
                'specs' => ['Jenis akun' => '1 profil di akun sharing', 'Kualitas' => 'Hingga 4K', 'Perangkat' => $devices],
            ],
            [
                'slug' => 'spotify-premium', 'name' => 'Spotify Premium', 'category' => 'streaming', 'account_type' => 'spotify',
                'image' => 'spotify-premium.jpg', 'prices' => [15000, 29000, 42000], 'compare' => 35000,
                'description' => 'Musik tanpa iklan, audio berkualitas tinggi, download offline, skip lagu sepuasnya.',
                'highlights' => ['Tanpa iklan', 'Kualitas audio tinggi', 'Download untuk offline', 'Skip tanpa batas'],
                'specs' => ['Jenis akun' => 'Upgrade akun pribadi kamu', 'Kualitas' => 'Very High (320 kbps)', 'Perangkat' => 'HP, desktop, web, speaker'],
            ],
            [
                'slug' => 'youtube-premium', 'name' => 'YouTube Premium', 'category' => 'streaming', 'account_type' => 'youtube',
                'image' => 'youtube-premium.jpg', 'prices' => [18000, 34000, 50000], 'compare' => 39000,
                'description' => 'YouTube tanpa iklan, putar di latar belakang, download video, plus YouTube Music Premium.',
                'highlights' => ['Bebas iklan', 'Putar di latar belakang dan picture-in-picture', 'Download video untuk offline', 'YouTube Music Premium'],
                'specs' => ['Jenis akun' => 'Upgrade akun Google pribadi', 'Region' => 'Aktif global', 'Perangkat' => 'HP, tablet, web, TV'],
            ],
            [
                'slug' => 'canva-pro', 'name' => 'Canva Pro', 'category' => 'tools', 'account_type' => 'canva',
                'image' => 'canva-pro.jpeg', 'prices' => [12000, 23000, 34000], 'compare' => 29000,
                'description' => 'Jutaan template, foto, dan elemen premium, hapus latar belakang, Magic Resize, dan 1 TB cloud.',
                'highlights' => ['Jutaan template dan aset premium', 'Hapus latar belakang 1 klik', 'Magic Resize dan Brand Kit', 'Penyimpanan cloud 1 TB'],
                'specs' => ['Jenis akun' => 'Undangan ke tim Canva Pro', 'Region' => 'Global', 'Perangkat' => 'Web, desktop, HP'],
            ],
            [
                'slug' => 'capcut-pro', 'name' => 'CapCut Pro', 'category' => 'tools', 'account_type' => 'capcut',
                'image' => 'capcut-pro.jpg', 'prices' => [25000, 48000, 70000], 'compare' => 55000,
                'description' => 'Edit video tanpa watermark, ekspor 4K 60fps, efek, transisi, filter, dan aset audio premium.',
                'highlights' => ['Ekspor tanpa watermark', 'Kualitas 4K 60fps', 'Efek, transisi, dan filter premium', 'Penyimpanan cloud lebih besar'],
                'specs' => ['Jenis akun' => 'Akun sharing terkelola', 'Region' => 'Global', 'Perangkat' => 'HP, desktop, web'],
            ],
        ];
    }
}
