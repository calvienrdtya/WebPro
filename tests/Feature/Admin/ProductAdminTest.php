<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        Storage::fake('local'); // file asli gambar (ImageOptimizer)
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Netflix Premium',
            'slug' => '',
            'category' => 'streaming',
            'account_type' => 'netflix',
            'description' => 'Streaming 4K.',
            'highlights' => ['Kualitas 4K', '1 profil pribadi'],
            'specs' => [['label' => 'Jenis akun', 'value' => 'Sharing']],
            'meta_title' => '',
            'meta_description' => '',
            'price' => 45000,
            'discount_percent' => 20,
            'sort_order' => 3,
            'is_active' => true,
        ], $overrides);
    }

    /** Produk dengan N penawaran varian (1..N bulan), harga akhir 10.000 x jumlah bulan. */
    private function productWithVariants(array $attrs = [], int $variants = 2): Product
    {
        $product = Product::factory()->create($attrs);

        foreach (range(1, $variants) as $months) {
            $variant = Variant::firstOrCreate(['months' => $months], ['title' => "$months Bulan"]);
            ProductVariant::factory()->create(['product_id' => $product->id, 'variant_id' => $variant->id, 'price' => $months * 20000, 'discount_percent' => 50]);
        }

        return $product->fresh('productVariants.variant');
    }

    // ---------- akses ----------

    public function test_guests_and_regular_users_cannot_reach_product_routes(): void
    {
        $product = $this->productWithVariants();
        $routes = [
            ['get', '/admin/produk'], ['get', '/admin/produk/baru'], ['post', '/admin/produk'],
            ['get', "/admin/produk/{$product->id}/ubah"], ['put', "/admin/produk/{$product->id}"],
            ['patch', "/admin/produk/{$product->id}/aktif"], ['delete', "/admin/produk/{$product->id}"],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertNull($product->fresh()->deleted_at);
    }

    // ---------- daftar ----------

    public function test_index_lists_products_ordered_with_price_range_and_pagination(): void
    {
        $b = $this->productWithVariants(['name' => 'Bravo', 'sort_order' => 2], 3);
        $a = $this->productWithVariants(['name' => 'Alpha', 'sort_order' => 2]);
        $first = $this->productWithVariants(['name' => 'Zulu', 'sort_order' => 1], 1);
        Product::factory()->count(10)->create(['sort_order' => 9]);

        $this->get('/admin/produk')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Products/Index')
                ->where('products.total', 13)
                ->where('products.per_page', 10)
                ->has('products.data', 10)
                ->where('products.data.0.name', 'Zulu')
                ->where('products.data.1.name', 'Alpha')
                ->where('products.data.2.name', 'Bravo')
                ->where('products.data.2.variants_count', 3)
                ->where('products.data.2.price_min', 10000)
                ->where('products.data.2.price_max', 30000)
                ->has('categories', 3));

        $this->get('/admin/produk?page=2')->assertInertia(fn ($page) => $page->has('products.data', 3));
    }

    public function test_index_filters_by_search_category_and_status(): void
    {
        $this->productWithVariants(['name' => 'Spotify Premium', 'slug' => 'spotify-premium', 'category' => 'streaming']);
        $this->productWithVariants(['name' => 'Canva Pro', 'slug' => 'canva-pro', 'category' => 'tools']);
        $this->productWithVariants(['name' => 'CapCut Pro', 'slug' => 'capcut-pro', 'category' => 'tools', 'is_active' => false]);

        $names = fn (string $query) => collect($this->get("/admin/produk?$query")->viewData('page')['props']['products']['data'])->pluck('name')->all();

        $this->assertSame(['Spotify Premium'], $names('q=spotify'));
        $this->assertSame(['Canva Pro'], $names('q=canva-pro'));
        $this->assertEqualsCanonicalizing(['Canva Pro', 'CapCut Pro'], $names('category=tools'));
        $this->assertSame(['CapCut Pro'], $names('category=tools&status=inactive'));
        $this->assertEqualsCanonicalizing(['Spotify Premium', 'Canva Pro'], $names('status=active'));
        $this->assertCount(3, $names('category=bukan-kategori&status=ngawur'));
    }

    // ---------- tambah ----------

    public function test_create_page_exposes_select_options(): void
    {
        $this->get('/admin/produk/baru')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Products/Create')
            ->has('options.categories', 3)
            ->has('options.account_types', 9));
    }

    public function test_store_creates_product_with_price_and_auto_slug(): void
    {
        $this->post('/admin/produk', $this->payload())
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $product = Product::firstOrFail();
        $this->assertSame('netflix-premium', $product->slug);
        $this->assertSame('streaming', $product->category->value);
        $this->assertSame('netflix', $product->account_type->value);
        $this->assertSame(['Kualitas 4K', '1 profil pribadi'], $product->highlights);
        $this->assertSame([['label' => 'Jenis akun', 'value' => 'Sharing']], $product->specs);
        $this->assertTrue($product->is_active);
        $this->assertSame(3, $product->sort_order);
        $this->assertSame(45000, $product->price);
        $this->assertSame(20, $product->discount_percent);
        $this->assertSame(36000, $product->final_price);
        $this->assertCount(0, $product->productVariants, 'varian dipasang dari menu Varian, bukan dari form produk');
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('product_variants', 'cogs'));
    }

    public function test_price_is_required_and_discount_is_a_percentage(): void
    {
        $cases = [
            'harga kosong' => [['price' => null], 'price'],
            'harga nol' => [['price' => 0], 'price'],
            'harga bukan angka' => [['price' => 'murah'], 'price'],
            'harga negatif' => [['price' => -1], 'price'],
            'diskon 100' => [['discount_percent' => 100], 'discount_percent'],
            'diskon negatif' => [['discount_percent' => -5], 'discount_percent'],
            'diskon pecahan' => [['discount_percent' => 12.5], 'discount_percent'],
        ];

        foreach ($cases as $name => [$override, $field]) {
            $errors = $this->post('/admin/produk', $this->payload($override))->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('products', 0);
    }

    public function test_empty_discount_means_no_discount_and_final_price_rounds_to_whole_rupiah(): void
    {
        $this->post('/admin/produk', $this->payload(['discount_percent' => null, 'price' => 45000]));
        $this->assertSame(0, Product::firstOrFail()->discount_percent);
        $this->assertSame(45000, Product::firstOrFail()->final_price);

        $product = Product::factory()->create(['price' => 10001, 'discount_percent' => 33]);
        $this->assertSame(6701, $product->final_price);
    }

    public function test_service_fee_is_optional_zero_means_free_and_edit_exposes_it(): void
    {
        \App\Models\ServiceFee::create(['name' => 'Biaya Admin', 'amount' => 1500, 'is_active' => true]);

        $this->post('/admin/produk', $this->payload(['service_fee' => '']));
        $product = Product::firstOrFail();
        $this->assertNull($product->service_fee);

        $this->put("/admin/produk/{$product->id}", $this->payload(['service_fee' => '0', 'slug' => $product->slug]))->assertSessionHasNoErrors();
        $this->assertSame(0, $product->fresh()->service_fee);
        $this->put("/admin/produk/{$product->id}", $this->payload(['service_fee' => '-5', 'slug' => $product->slug]))->assertSessionHasErrors('service_fee');

        $this->get("/admin/produk/{$product->id}/ubah")->assertInertia(fn ($page) => $page
            ->where('product.service_fee', 0)->where('options.service_fee', 1500));
    }

    public function test_product_list_and_edit_expose_price_and_discount(): void
    {
        $product = Product::factory()->create(['price' => 45000, 'discount_percent' => 20]);

        $this->get('/admin/produk')->assertInertia(fn ($page) => $page
            ->where('products.data.0.price', 45000)->where('products.data.0.discount_percent', 20)->where('products.data.0.final_price', 36000));
        $this->get("/admin/produk/{$product->id}/ubah")->assertInertia(fn ($page) => $page
            ->where('product.price', 45000)->where('product.discount_percent', 20));
    }

    public function test_numbers_typed_with_leading_zeros_are_accepted(): void
    {
        $this->post('/admin/produk', $this->payload(['price' => '045000', 'discount_percent' => '05', 'sort_order' => '007']))->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertSame([45000, 5, 7], [$product->price, $product->discount_percent, $product->sort_order]);
    }

    public function test_store_makes_slugs_unique_and_keeps_a_custom_one(): void
    {
        $this->post('/admin/produk', $this->payload());
        $this->post('/admin/produk', $this->payload());
        $this->post('/admin/produk', $this->payload(['slug' => 'nflx-murah']));

        $this->assertSame(['netflix-premium', 'netflix-premium-2', 'nflx-murah'], Product::orderBy('id')->pluck('slug')->all());
    }

    public function test_store_can_create_an_inactive_product_with_only_the_required_fields(): void
    {
        $this->post('/admin/produk', [
            'name' => 'Produk Minimal', 'category' => 'tools', 'is_active' => false, 'price' => 1000,
        ])->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertFalse($product->is_active);
        $this->assertNull($product->account_type);
        $this->assertSame([], $product->highlights);
        $this->assertSame(0, $product->sort_order);
        $this->assertSame(0, $product->discount_percent);
    }

    public function test_store_validation_rules(): void
    {
        $cases = [
            'nama kosong' => [['name' => ''], 'name'],
            'kategori ngawur' => [['category' => 'game'], 'category'],
            'jenis akun ngawur' => [['account_type' => 'tiktok'], 'account_type'],
            'slug berformat salah' => [['slug' => 'Slug Salah!'], 'slug'],
            'poin terlalu banyak' => [['highlights' => array_fill(0, 11, 'x')], 'highlights'],
            'spesifikasi tanpa isi' => [['specs' => [['label' => 'Jenis', 'value' => '']]], 'specs.0.value'],
            'gambar bukan gambar' => [['image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], 'image'],
            'gambar terlalu besar' => [['image' => UploadedFile::fake()->image('besar.jpg')->size(3000)], 'image'],
            'urutan negatif' => [['sort_order' => -1], 'sort_order'],
        ];

        foreach ($cases as $name => [$override, $field]) {
            try {
                $this->post('/admin/produk', $this->payload($override))->assertSessionHasErrors($field);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail("Kasus '$name' harus menolak field '$field': ".$e->getMessage());
            }
        }

        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_a_slug_already_used_even_by_a_deleted_product(): void
    {
        Product::factory()->create(['slug' => 'dipakai'])->delete();

        $this->post('/admin/produk', $this->payload(['slug' => 'dipakai']))->assertSessionHasErrors('slug');
    }

    public function test_store_saves_an_uploaded_image_on_the_uploads_disk(): void
    {
        $this->post('/admin/produk', $this->payload(['image' => UploadedFile::fake()->image('cover.png', 600, 400)]))
            ->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('uploads')->assertExists($product->image);
        $this->assertStringContainsString($product->image, $product->image_url);
    }


    // ---------- ubah ----------

    public function test_edit_page_exposes_the_product_with_a_summary_of_its_variants(): void
    {
        $product = $this->productWithVariants(['name' => 'Alpha'], 2);
        Variant::where('months', 2)->update(['is_active' => false]);

        $this->get("/admin/produk/{$product->id}/ubah")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Products/Edit')
            ->where('product.name', 'Alpha')
            ->has('product.variants', 2)
            ->where('product.variants.0.title', '1 Bulan')
            ->where('product.variants.0.final_price', 10000)
            ->where('product.variants.0.mode_label', 'Buat sendiri')
            ->where('product.variants.1.months', 2)
            ->where('product.variants.1.final_price', 20000)
            ->where('product.variants.1.is_active', false)
            ->has('options.categories', 3));
    }

    public function test_update_changes_fields_and_leaves_variants_alone(): void
    {
        $product = $this->productWithVariants(['name' => 'Alpha', 'slug' => 'alpha'], 2);

        $this->put("/admin/produk/{$product->id}", $this->payload(['name' => 'Alpha Baru', 'slug' => 'alpha', 'price' => 50000, 'discount_percent' => 10]))
            ->assertRedirect(route('admin.products.index'))->assertSessionHas('success');

        $product->refresh();
        $this->assertSame('Alpha Baru', $product->name);
        $this->assertSame(45000, $product->final_price);
        $this->assertCount(2, $product->productVariants);
    }

    public function test_update_keeps_the_slug_when_blank_and_rejects_another_products_slug(): void
    {
        $product = $this->productWithVariants(['slug' => 'asli']);
        $other = Product::factory()->create(['slug' => 'milik-lain']);

        $this->put("/admin/produk/{$product->id}", $this->payload(['slug' => '']))->assertSessionHasNoErrors();
        $this->assertSame('asli', $product->fresh()->slug);

        $this->put("/admin/produk/{$product->id}", $this->payload(['slug' => 'milik-lain']))->assertSessionHasErrors('slug');
        $this->put("/admin/produk/{$product->id}", $this->payload(['slug' => 'asli']))->assertSessionHasNoErrors();
    }

    public function test_update_replaces_and_removes_images_and_deletes_the_old_file(): void
    {
        $this->post('/admin/produk', $this->payload(['image' => UploadedFile::fake()->image('lama.png')]));
        $product = Product::firstOrFail();
        $old = $product->image;

        $this->put("/admin/produk/{$product->id}", $this->payload(['image' => UploadedFile::fake()->image('baru.jpg')]))->assertSessionHasNoErrors();
        $new = $product->fresh()->image;
        $this->assertNotSame($old, $new);
        Storage::disk('uploads')->assertMissing($old);
        Storage::disk('uploads')->assertExists($new);

        $this->put("/admin/produk/{$product->id}", $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($new, $product->fresh()->image);
        Storage::disk('uploads')->assertExists($new);

        $this->put("/admin/produk/{$product->id}", $this->payload(['remove_image' => true]))->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->image);
        Storage::disk('uploads')->assertMissing($new);
    }

    // ---------- status & hapus ----------

    public function test_toggle_flips_the_active_flag(): void
    {
        $product = $this->productWithVariants(['is_active' => true]);

        $this->patch("/admin/produk/{$product->id}/aktif")->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($product->fresh()->is_active);

        $this->patch("/admin/produk/{$product->id}/aktif");
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_destroy_soft_deletes_and_hides_the_product(): void
    {
        $product = $this->productWithVariants(['name' => 'Hapus Aku']);

        $this->delete("/admin/produk/{$product->id}")->assertRedirect(route('admin.products.index'))->assertSessionHas('success');

        $this->assertSoftDeleted($product);
        $this->assertDatabaseCount('product_variants', 2);
        $this->get('/admin/produk')->assertInertia(fn ($page) => $page->where('products.total', 0));
        $this->get("/admin/produk/{$product->id}/ubah")->assertNotFound();
        $this->delete("/admin/produk/{$product->id}")->assertNotFound();
    }

    // ---------- model ----------

    public function test_offer_prices_follow_the_product_or_their_own_values(): void
    {
        $product = Product::factory()->create(['price' => 45000, 'discount_percent' => 20]);
        $three = Variant::factory()->months(3)->create();
        $one = Variant::factory()->months(1)->create();

        $follow = ProductVariant::factory()->fromProduct()->create(['product_id' => $product->id, 'variant_id' => $three->id]);
        $own = ProductVariant::factory()->create(['product_id' => $product->id, 'variant_id' => $one->id, 'price' => 55000, 'discount_percent' => 0]);

        $this->assertSame([135000, 20, 108000], [$follow->list_price, $follow->discount, $follow->final_price], 'harga produk x 3 bulan, diskon produk');
        $this->assertSame([55000, 0, 55000], [$own->list_price, $own->discount, $own->final_price]);

        // Harga produk diubah: penawaran yang mengikuti ikut berubah, yang buat sendiri tidak.
        $product->update(['price' => 50000]);
        $follow = $follow->fresh();
        $this->assertSame(120000, $follow->final_price);
        $this->assertSame(55000, $own->fresh()->final_price);
    }

    public function test_unique_slug_skips_taken_and_trashed_slugs(): void
    {
        Product::factory()->create(['slug' => 'canva-pro']);
        Product::factory()->create(['slug' => 'canva-pro-2'])->delete();

        $this->assertSame('canva-pro-3', Product::uniqueSlug('Canva Pro'));
        $this->assertSame('produk', Product::uniqueSlug('!!!'));
    }

}
