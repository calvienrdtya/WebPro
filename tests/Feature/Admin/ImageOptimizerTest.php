<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\UploadedImage;
use App\Models\User;
use App\Support\ImageOptimizer;
use App\Support\StoreProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    /** Gradasi halus seperti poster (PNG boros, WebP hemat); dengan $alpha: logo di latar transparan. */
    private function png(int $w, int $h, bool $alpha = false): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x += 2) {
                $c = $alpha
                    ? imagecolorallocatealpha($im, 0, 0, 0, 127)
                    : imagecolorallocate($im, (int) (255 * $x / $w), (int) (255 * $y / $h), (int) (128 + 127 * sin(($x + $y) / 40)));
                imagesetpixel($im, $x, $y, $c);
                imagesetpixel($im, $x + 1, $y, $c);
            }
        }
        if ($alpha) {
            imagefilledrectangle($im, (int) ($w / 4), (int) ($h / 4), (int) ($w * 3 / 4), (int) ($h * 3 / 4), imagecolorallocatealpha($im, 200, 30, 30, 0));
        }
        $file = tempnam(sys_get_temp_dir(), 'img').'.png';
        imagepng($im, $file);

        return new UploadedFile($file, 'poster.png', 'image/png', null, true);
    }

    private function productData(array $extra = []): array
    {
        return array_merge(['name' => 'Produk Uji', 'category' => 'ai', 'price' => 50000, 'discount_percent' => 0, 'is_active' => true], $extra);
    }

    public function test_upload_keeps_the_original_and_shows_a_smaller_webp_with_a_jpg_share_variant(): void
    {
        $upload = $this->png(1600, 1600);
        $path = app(ImageOptimizer::class)->store($upload, 'products', 'product');

        $row = UploadedImage::sole();
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame([1050, 1050, 90, true], [$row->width, $row->height, $row->quality, $row->optimized]);
        $this->assertSame([1600, 1600], [$row->original_width, $row->original_height]);
        $this->assertLessThan($row->original_size, $row->size);
        Storage::disk('uploads')->assertExists($path);
        Storage::disk('local')->assertExists($row->original_path);
        $this->assertSame(filesize($upload->getRealPath()), strlen(Storage::disk('local')->get($row->original_path)));
        $this->assertStringEndsWith('.jpg', $row->share_path);
        $this->assertStringContainsString($row->share_path, app(ImageOptimizer::class)->shareUrl($path));
    }

    public function test_small_images_are_never_enlarged_and_transparency_is_kept(): void
    {
        $path = app(ImageOptimizer::class)->store($this->png(80, 60, alpha: true), 'store/campus', 'campus');
        // Logo kecil: tidak diperbesar; bila hasil WebP lebih kecil, sudut tetap transparan.

        $row = UploadedImage::sole();
        $this->assertSame([80, 60], [$row->width, $row->height]);
        if ($row->optimized) {
            $im = imagecreatefromstring(Storage::disk('uploads')->get($path));
            $this->assertGreaterThan(0, (imagecolorat($im, 0, 0) >> 24) & 0x7F, 'piksel transparan tetap transparan');
        }
    }

    public function test_large_transparent_logo_is_resized_and_stays_transparent(): void
    {
        $path = app(ImageOptimizer::class)->store($this->png(1600, 800, alpha: true), 'store', 'logo');

        $row = UploadedImage::sole();
        $this->assertTrue($row->optimized);
        $this->assertSame([256, 128], [$row->width, $row->height]);
        $im = imagecreatefromstring(Storage::disk('uploads')->get($path));
        $this->assertSame(127, (imagecolorat($im, 2, 2) >> 24) & 0x7F, 'sudut tetap transparan penuh');
        $this->assertSame(0, (imagecolorat($im, 128, 64) >> 24) & 0x7F, 'isi logo tetap tidak transparan');
    }

    public function test_quality_follows_the_setting_and_is_validated(): void
    {
        $this->put('/admin/pengaturan/gambar', ['quality' => 50])->assertSessionHasErrors('quality');
        $this->put('/admin/pengaturan/gambar', ['quality' => 95])->assertSessionHas('success');

        app(ImageOptimizer::class)->store($this->png(1200, 800), 'store', 'hero');
        $this->assertSame([95, 1120, 747], [UploadedImage::sole()->quality, UploadedImage::sole()->width, UploadedImage::sole()->height]);
    }

    public function test_product_upload_and_per_image_switch_between_original_and_optimized(): void
    {
        $this->post('/admin/produk', $this->productData(['image' => $this->png(1300, 1300)]))->assertRedirect();
        $product = Product::sole();
        $optimized = $product->image;
        $this->assertStringEndsWith('.webp', $optimized);

        $this->get("/admin/produk/{$product->id}/ubah")->assertInertia(fn (Assert $p) => $p
            ->where('product.image_info.optimized', true)->where('product.image_info.can_original', true)->where('product.image_info.width', 1050));

        // Pakai file asli: isi file sama persis dengan unggahan.
        $this->patch("/admin/produk/{$product->id}/gambar", ['action' => 'original'])->assertSessionHas('success');
        $product->refresh();
        $this->assertStringEndsWith('.png', $product->image);
        Storage::disk('uploads')->assertMissing($optimized);
        $row = UploadedImage::sole();
        $this->assertSame(Storage::disk('local')->get($row->original_path), Storage::disk('uploads')->get($product->image));
        $this->assertFalse($row->optimized);

        // Optimalkan lagi.
        $this->patch("/admin/produk/{$product->id}/gambar", ['action' => 'optimize'])->assertSessionHas('success');
        $this->assertStringEndsWith('.webp', $product->fresh()->image);
        $this->assertTrue(UploadedImage::sole()->optimized);

        // Ganti gambar: file lama, file asli, dan catatannya ikut terhapus.
        $oldOriginal = UploadedImage::sole()->original_path;
        $this->put("/admin/produk/{$product->id}", $this->productData(['image' => $this->png(500, 500)]))->assertRedirect();
        Storage::disk('local')->assertMissing($oldOriginal);
        $this->assertSame(1, UploadedImage::count());
    }

    public function test_store_images_and_default_campus_logos_can_be_optimized_and_reverted(): void
    {
        $this->post('/admin/tampilan/beranda', ['hero_title' => 'Akun premium', 'hero_image' => $this->png(1600, 900)])->assertSessionHasNoErrors();
        $hero = StoreProfile::uploaded('hero_image');
        $this->assertStringEndsWith('.webp', $hero);

        $this->patch('/admin/tampilan/gambar/hero_image', ['action' => 'original'])->assertSessionHas('success');
        $this->assertStringEndsWith('.png', StoreProfile::uploaded('hero_image'));

        // Gambar bawaan di public/assets: dioptimalkan menjadi unggahan, file bawaan tetap ada.
        $default = config('store.logo');
        $this->patch('/admin/tampilan/gambar/logo', ['action' => 'optimize'])->assertSessionHas('success');
        $this->assertNotNull(StoreProfile::uploaded('logo'));
        $this->assertFileExists(public_path($default));

        $this->patch('/admin/tampilan/gambar/favicon', ['action' => 'optimize'])->assertNotFound();
        $this->patch('/admin/tampilan/gambar/logo', ['action' => 'hapus'])->assertSessionHasErrors('action');
    }

    public function test_command_optimizes_existing_images_once_and_keeps_originals(): void
    {
        Storage::disk('uploads')->put('products/lama.png', file_get_contents($this->png(1400, 1400)->getRealPath()));
        $product = Product::factory()->create(['image' => 'products/lama.png']);

        $this->artisan('images:optimize')->assertSuccessful();
        $this->assertStringEndsWith('.webp', $product->fresh()->image);
        Storage::disk('uploads')->assertMissing('products/lama.png');
        $count = UploadedImage::count();

        $this->artisan('images:optimize')->assertSuccessful();
        $this->assertSame($count, UploadedImage::count(), 'dijalankan ulang tidak memproses dua kali');
    }

    public function test_unreadable_images_fall_back_to_the_original_without_error(): void
    {
        $file = UploadedFile::fake()->create('rusak.png', 10, 'image/png');
        $path = app(ImageOptimizer::class)->store($file, 'products', 'product');

        $this->assertStringEndsWith('.png', $path);
        $this->assertFalse(UploadedImage::sole()->optimized);
        Storage::disk('uploads')->assertExists($path);
    }
}
