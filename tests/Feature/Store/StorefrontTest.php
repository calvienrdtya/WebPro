<?php

namespace Tests\Feature\Store;

use App\Enums\AccountStatus;
use App\Enums\PriceMode;
use App\Enums\ProductCategory;
use App\Models\Account;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Variant;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'Netflix Premium', 'slug' => 'netflix-premium', 'category' => ProductCategory::Streaming,
            'price' => 65000, 'discount_percent' => 54,
        ], $attrs));
    }

    private function attachVariant(Product $product, int $months, array $attrs = [], bool $active = true): ProductVariant
    {
        $variant = Variant::factory()->create(['months' => $months, 'title' => "{$months} Bulan", 'is_active' => $active]);

        return ProductVariant::create(array_merge(['product_id' => $product->id, 'variant_id' => $variant->id, 'price_mode' => PriceMode::FromProduct], $attrs));
    }

    // ---------- opsi jual ----------

    public function test_sale_options_default_to_one_month_at_product_price_when_no_active_variant(): void
    {
        $product = $this->product();
        $this->attachVariant($product, 3, [], active: false); // varian nonaktif diabaikan

        $options = $product->fresh()->load('productVariants.variant')->saleOptions();

        $this->assertCount(1, $options);
        $this->assertSame([null, 1, 65000, 29900], [$options[0]['id'], $options[0]['months'], $options[0]['list_price'], $options[0]['final_price']]);
    }

    public function test_sale_options_list_active_variants_shortest_first_with_their_own_prices(): void
    {
        $product = $this->product();
        $this->attachVariant($product, 3, ['price_mode' => PriceMode::Custom, 'price' => 180000, 'discount_percent' => 50]);
        $this->attachVariant($product, 1);

        $options = $product->fresh()->load('productVariants.variant')->saleOptions();

        $this->assertSame([1, 3], $options->pluck('months')->all());
        $this->assertSame([29900, 90000], $options->pluck('final_price')->all());
        $this->assertSame([54, 50], $options->pluck('discount')->all());
    }

    // ---------- beranda ----------

    public function test_home_lists_only_active_products_with_price_and_discount(): void
    {
        $this->product();
        Product::factory()->inactive()->create(['name' => 'Produk Tersembunyi']);

        $this->get('/')->assertOk()
            ->assertSee('Netflix Premium')
            ->assertSee('Rp 29.900', false)
            ->assertSee('Rp 65.000', false)
            ->assertSee('54%')
            ->assertDontSee('Produk Tersembunyi')
            ->assertSee('"@type":"Organization"', false);
    }

    public function test_home_works_with_an_empty_catalog(): void
    {
        $this->get('/')->assertOk()->assertSee('Produk akan tampil di sini');
    }

    public function test_best_sellers_are_ordered_by_sold_accounts(): void
    {
        $slow = $this->product(['name' => 'Produk Sepi', 'slug' => 'sepi', 'sort_order' => 1]);
        $hot = $this->product(['name' => 'Produk Laris', 'slug' => 'laris', 'sort_order' => 2]);
        Account::factory()->count(2)->create(['product_id' => $hot->id, 'status' => AccountStatus::Sold]);

        $this->get('/')->assertOk()->assertSeeInOrder(['Pilihan Terbaik', 'Produk Laris', 'Produk Sepi']);
    }

    // ---------- katalog ----------

    public function test_catalog_filters_by_category_and_search_and_noindexes_search(): void
    {
        $this->product();
        $this->product(['name' => 'ChatGPT Plus', 'slug' => 'chatgpt-plus', 'category' => ProductCategory::Ai]);
        // Footer juga menampilkan nama produk, jadi isi grid diperiksa lewat data view.
        $grid = fn ($response) => $response->viewData('products')->pluck('slug')->sort()->values()->all();

        $all = $this->get('/produk')->assertOk()
            ->assertSee('rel="canonical" href="'.url('/produk').'"', false)->assertDontSee('noindex', false);
        $this->assertSame(['chatgpt-plus', 'netflix-premium'], $grid($all));

        $ai = $this->get('/produk?kategori=ai')->assertOk()
            ->assertSee('<title>Akun AI Premium — ProAkses</title>', false)->assertDontSee('Akun Akun');
        $this->assertSame(['chatgpt-plus'], $grid($ai));

        $search = $this->get('/produk?q=netflix')->assertOk()->assertSee('noindex', false);
        $this->assertSame(['netflix-premium'], $grid($search));

        $this->assertSame(['chatgpt-plus', 'netflix-premium'], $grid($this->get('/produk?kategori=ngawur')->assertOk()));
        $this->get('/produk?q=tidakada')->assertOk()->assertSee('Produk tidak ditemukan');
    }

    // ---------- detail ----------

    public function test_product_page_has_seo_meta_and_json_ld_without_fake_ratings(): void
    {
        $product = $this->product(['meta_title' => 'Beli Netflix Murah', 'meta_description' => 'Netflix premium harga rupiah.']);
        $this->attachVariant($product, 1);
        $this->attachVariant($product, 3, ['price_mode' => PriceMode::Custom, 'price' => 180000, 'discount_percent' => 50]);
        Account::factory()->count(3)->create(['product_id' => $product->id]);

        $response = $this->get('/produk/netflix-premium')->assertOk()
            ->assertSee('<title>Beli Netflix Murah</title>', false)
            ->assertSee('content="Netflix premium harga rupiah."', false)
            ->assertSee('rel="canonical" href="'.url('/produk/netflix-premium').'"', false)
            ->assertSee('property="og:type" content="product"', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"@type":"AggregateOffer"', false)
            ->assertSee('"lowPrice":29900', false)
            ->assertSee('"highPrice":90000', false)
            ->assertSee('https://schema.org/InStock', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('Sisa stok 3')
            ->assertSee('Pilih durasi');

        $this->assertStringNotContainsString('aggregateRating', $response->getContent());
        $this->assertStringNotContainsString('AggregateRating', $response->getContent());
    }

    public function test_single_option_product_uses_plain_offer_hides_duration_box_and_flags_out_of_stock(): void
    {
        $this->product();

        $this->get('/produk/netflix-premium')->assertOk()
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('https://schema.org/OutOfStock', false)
            ->assertDontSee('Pilih durasi')
            ->assertSee('Stok habis');
    }

    public function test_unknown_or_inactive_product_is_404(): void
    {
        Product::factory()->inactive()->create(['slug' => 'tersembunyi']);

        $this->get('/produk/tidak-ada')->assertNotFound();
        $this->get('/produk/tersembunyi')->assertNotFound();
    }

    public function test_warranty_choices_show_only_active_and_applicable_ones_with_an_implicit_no_warranty(): void
    {
        $product = $this->product();
        $other = Product::factory()->create();
        Warranty::factory()->forAllProducts()->create(['name' => 'Garansi Semua', 'price' => 5000]);
        $only = Warranty::factory()->create(['name' => 'Garansi Khusus Ini', 'price' => 8000]);
        $only->products()->sync([$product->id]);
        Warranty::factory()->create(['name' => 'Garansi Produk Lain'])->products()->sync([$other->id]);
        Warranty::factory()->forAllProducts()->inactive()->create(['name' => 'Garansi Mati']);

        $this->get('/produk/netflix-premium')->assertOk()
            ->assertDontSee('Tanpa garansi')
            ->assertSee('Garansi Semua')->assertSee('Garansi Khusus Ini')
            ->assertSee('Rp 5.000', false)
            ->assertDontSee('Garansi Produk Lain')->assertDontSee('Garansi Mati');
    }

    public function test_related_products_prefer_the_same_category_and_exclude_itself(): void
    {
        $this->product();
        $this->product(['name' => 'Spotify Premium', 'slug' => 'spotify', 'category' => ProductCategory::Streaming]);
        $this->product(['name' => 'Canva Pro', 'slug' => 'canva', 'category' => ProductCategory::Tools]);

        $this->get('/produk/netflix-premium')->assertOk()->assertSeeInOrder(['Produk terkait', 'Spotify Premium', 'Canva Pro']);
    }

    public function test_description_html_is_escaped_in_page_and_json_ld(): void
    {
        $this->product(['description' => '<script>alert(1)</script> aman']);

        $response = $this->get('/produk/netflix-premium')->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getContent());
    }

    // ---------- SEO ----------

    public function test_sitemap_lists_home_catalog_and_active_products_only(): void
    {
        $this->product();
        Product::factory()->inactive()->create(['slug' => 'tersembunyi']);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $response->assertSee('<loc>'.url('/').'</loc>', false)
            ->assertSee('<loc>'.url('/produk').'</loc>', false)
            ->assertSee('<loc>'.url('/produk/netflix-premium').'</loc>', false)
            ->assertDontSee('tersembunyi');
    }

    public function test_robots_blocks_private_areas_and_points_to_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin', false)->assertSee('Disallow: /profil', false)->assertSee('Disallow: /login', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_pages_do_not_expose_account_credentials_or_cogs(): void
    {
        $product = $this->product();
        Account::factory()->create(['product_id' => $product->id, 'email' => 'rahasia@akun.test', 'password' => 'sandiRahasia1', 'cogs' => 12345]);

        foreach (['/', '/produk', '/produk/netflix-premium'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('rahasia@akun.test')->assertDontSee('sandiRahasia1')->assertDontSee('12345');
        }
    }

    // ---------- navbar ----------

    public function test_navbar_marks_the_current_section_as_active(): void
    {
        $this->product();
        $active = fn (string $url) => preg_match_all('/<a href="([^"]+)"\s+aria-current="page"\s+class="[^"]*nav-active/', $this->get($url)->getContent(), $m) ? $m[1] : [];

        $this->assertSame([route('products.index')], $active('/produk'));
        $this->assertSame([route('products.index')], $active('/produk/netflix-premium'));
        $this->assertSame([route('vouchers.index')], $active('/voucher'));
        $this->assertSame([route('faq')], $active('/faq'));
        $this->assertSame([], $active('/'));
    }
}
