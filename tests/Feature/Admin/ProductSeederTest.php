<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Variant;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        Storage::fake('local'); // file asli gambar (ImageOptimizer)
    }

    public function test_it_seeds_the_nine_frontend_products_with_three_shared_variants(): void
    {
        $this->seed(ProductSeeder::class);

        $this->assertSame(9, Product::count());
        $this->assertSame([1, 2, 3], Variant::orderBy('months')->pluck('months')->all(), 'tiga templat dipakai bersama semua produk');
        $this->assertSame(27, ProductVariant::count());
        $this->assertSame(
            ['chatgpt-plus', 'claude-pro', 'netflix-premium', 'disney-hotstar', 'hbo-max', 'spotify-premium', 'youtube-premium', 'canva-pro', 'capcut-pro'],
            Product::orderBy('sort_order')->pluck('slug')->all(),
        );
    }

    public function test_one_month_follows_the_product_and_longer_variants_match_the_frontend_prices(): void
    {
        $this->seed(ProductSeeder::class);

        $finals = fn (string $slug) => Product::where('slug', $slug)->with('productVariants.variant')->firstOrFail()->offers()->pluck('final_price')->all();

        // 1 bulan = harga produk (dibulatkan dari persen diskon); 2 dan 3 bulan mendekati tabel harga frontend.
        $netflix = $finals('netflix-premium');
        $this->assertSame(29900, $netflix[0]);
        $this->assertEqualsWithDelta(57000, $netflix[1], 1000);
        $this->assertEqualsWithDelta(84000, $netflix[2], 1000);
        $this->assertEqualsWithDelta(45000, $finals('chatgpt-plus')[0], 100);

        $product = fn (string $slug) => Product::where('slug', $slug)->firstOrFail();
        $this->assertSame([79000, 43], [$product('chatgpt-plus')->price, $product('chatgpt-plus')->discount_percent]);
        $this->assertSame([65000, 54], [$product('netflix-premium')->price, $product('netflix-premium')->discount_percent]);

        $one = ProductVariant::whereHas('variant', fn ($q) => $q->where('months', 1))->get();
        $this->assertCount(9, $one);
        $this->assertSame(['from_product'], $one->pluck('price_mode')->map->value->unique()->values()->all());

        // Setiap produk makin murah per bulan untuk durasi lebih panjang.
        foreach (Product::with('productVariants.variant')->get() as $p) {
            $offers = $p->offers();
            $this->assertLessThan($offers[0]->final_price, $offers[1]->final_price / 2, $p->slug);
            $this->assertLessThan($offers[1]->final_price / 2, $offers[2]->final_price / 3, $p->slug);
        }
    }

    public function test_every_product_has_an_image_content_and_an_account_type(): void
    {
        $this->seed(ProductSeeder::class);

        foreach (Product::all() as $product) {
            Storage::disk('uploads')->assertExists($product->image);
            $this->assertNotEmpty($product->highlights, $product->slug);
            $this->assertNotEmpty($product->specs, $product->slug);
            $this->assertNotNull($product->account_type, $product->slug);
        }
    }

    public function test_running_it_twice_does_not_duplicate_or_overwrite_admin_edits(): void
    {
        $this->seed(ProductSeeder::class);
        Product::where('slug', 'canva-pro')->update(['name' => 'Canva Pro (diedit admin)']);

        $this->seed(ProductSeeder::class);

        $this->assertSame(9, Product::count());
        $this->assertSame(3, Variant::count());
        $this->assertSame(27, ProductVariant::count());
        $this->assertSame('Canva Pro (diedit admin)', Product::where('slug', 'canva-pro')->value('name'));
    }
}
