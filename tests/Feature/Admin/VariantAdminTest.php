<?php

namespace Tests\Feature\Admin;

use App\Enums\PriceMode;
use App\Models\Account;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => '3 Bulan',
            'months' => 3,
            'is_active' => true,
            'products' => [],
        ], $overrides);
    }

    private function row(Product $product, string $mode = 'from_product', ?int $price = null, ?int $discount = null): array
    {
        return ['product_id' => $product->id, 'price_mode' => $mode, 'price' => $price, 'discount_percent' => $discount];
    }

    // ---------- akses ----------

    public function test_guests_and_regular_users_cannot_reach_variant_routes(): void
    {
        $variant = Variant::factory()->create();
        $routes = [
            ['get', '/admin/varian'], ['post', '/admin/varian'], ['put', "/admin/varian/{$variant->id}"],
            ['patch', "/admin/varian/{$variant->id}/aktif"], ['delete', "/admin/varian/{$variant->id}"],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertNotNull($variant->fresh());
    }

    // ---------- daftar ----------

    public function test_index_lists_variants_by_months_with_their_priced_products(): void
    {
        $netflix = Product::factory()->create(['name' => 'Netflix', 'price' => 45000, 'discount_percent' => 20]);
        $disney = Product::factory()->create(['name' => 'Disney', 'price' => 30000, 'discount_percent' => 0]);
        $three = Variant::factory()->months(3)->create();
        $one = Variant::factory()->months(1)->create();
        ProductVariant::factory()->fromProduct()->create(['product_id' => $netflix->id, 'variant_id' => $three->id]);
        ProductVariant::factory()->create(['product_id' => $disney->id, 'variant_id' => $three->id, 'price' => 80000, 'discount_percent' => 10]);

        $this->get('/admin/varian')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Variants/Index')
            ->where('variants.0.id', $one->id)
            ->where('variants.0.products', [])
            ->where('variants.1.id', $three->id)
            ->has('variants.1.products', 2)
            ->where('variants.1.products.0.name', 'Disney')
            ->where('variants.1.products.0.final_price', 72000)
            ->where('variants.1.products.1.name', 'Netflix')
            ->where('variants.1.products.1.final_price', 108000, '45.000 x 3 bulan, diskon 20%')
            ->has('products', 2)
            ->has('modes', 2));
    }

    public function test_index_gives_the_form_each_products_base_price_and_average_cost(): void
    {
        $product = Product::factory()->create(['name' => 'Netflix', 'price' => 45000, 'discount_percent' => 20]);
        Account::factory()->create(['product_id' => $product->id, 'cogs' => 20000]);
        Account::factory()->create(['product_id' => $product->id, 'cogs' => 30000]);
        Account::factory()->create(['product_id' => $product->id, 'cogs' => null]);
        Product::factory()->create(['name' => 'Tanpa Akun']);

        $this->get('/admin/varian')->assertInertia(fn ($page) => $page
            ->where('products.0', ['id' => $product->id, 'name' => 'Netflix', 'price' => 45000, 'discount_percent' => 20, 'final_price' => 36000, 'average_cost' => 25000])
            ->where('products.1.average_cost', null));
    }

    // ---------- tambah ----------

    public function test_store_creates_a_variant_with_priced_products(): void
    {
        $netflix = Product::factory()->create();
        $disney = Product::factory()->create();

        $this->post('/admin/varian', $this->payload(['products' => [
            $this->row($netflix),
            $this->row($disney, 'custom', 85000, 15),
        ]]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $variant = Variant::firstOrFail();
        $this->assertSame(['3 Bulan', 3, true], [$variant->title, $variant->months, $variant->is_active]);

        $rows = $variant->productVariants()->orderBy('product_id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([PriceMode::FromProduct, null, 0], [$rows[0]->price_mode, $rows[0]->price, $rows[0]->discount_percent]);
        $this->assertSame([PriceMode::Custom, 85000, 15], [$rows[1]->price_mode, $rows[1]->price, $rows[1]->discount_percent]);
    }

    public function test_a_variant_can_be_created_without_any_product(): void
    {
        $this->post('/admin/varian', $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('variants', 1);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_from_product_rows_ignore_any_price_that_was_sent(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/varian', $this->payload(['products' => [$this->row($product, 'from_product', 999, 50)]]))->assertSessionHasNoErrors();

        $row = ProductVariant::firstOrFail();
        $this->assertNull($row->price);
        $this->assertSame(0, $row->discount_percent);
    }

    public function test_custom_discount_may_be_empty_and_means_zero(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/varian', $this->payload(['products' => [$this->row($product, 'custom', 55000, null)]]))->assertSessionHasNoErrors();

        $this->assertSame(0, ProductVariant::firstOrFail()->discount_percent);
    }

    public function test_numbers_typed_with_leading_zeros_are_accepted(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/varian', $this->payload(['months' => '03', 'products' => [
            ['product_id' => (string) $product->id, 'price_mode' => 'custom', 'price' => '0085000', 'discount_percent' => '05'],
        ]]))->assertSessionHasNoErrors();

        $row = ProductVariant::firstOrFail();
        $this->assertSame(3, Variant::firstOrFail()->months);
        $this->assertSame([85000, 5], [$row->price, $row->discount_percent]);
    }

    public function test_store_validation_rules(): void
    {
        $product = Product::factory()->create();
        $gone = Product::factory()->create();
        $gone->delete();

        $cases = [
            'title kosong' => [['title' => ''], 'title'],
            'title terlalu panjang' => [['title' => str_repeat('a', 31)], 'title'],
            'bulan kosong' => [['months' => null], 'months'],
            'bulan nol' => [['months' => 0], 'months'],
            'bulan 13' => [['months' => 13], 'months'],
            'bulan pecahan' => [['months' => 1.5], 'months'],
            'produk tidak ada' => [['products' => [['product_id' => 9999, 'price_mode' => 'from_product']]], 'products.0.product_id'],
            'produk sudah dihapus' => [['products' => [['product_id' => $gone->id, 'price_mode' => 'from_product']]], 'products.0.product_id'],
            'produk kembar' => [['products' => [$this->row($product), $this->row($product)]], 'products.1.product_id'],
            'mode ngawur' => [['products' => [$this->row($product, 'gratis')]], 'products.0.price_mode'],
            'custom tanpa harga' => [['products' => [$this->row($product, 'custom', null)]], 'products.0.price'],
            'harga nol' => [['products' => [$this->row($product, 'custom', 0)]], 'products.0.price'],
            'harga bukan angka' => [['products' => [['product_id' => $product->id, 'price_mode' => 'custom', 'price' => 'murah']]], 'products.0.price'],
            'diskon 100' => [['products' => [$this->row($product, 'custom', 1000, 100)]], 'products.0.discount_percent'],
            'diskon negatif' => [['products' => [$this->row($product, 'custom', 1000, -1)]], 'products.0.discount_percent'],
        ];

        foreach ($cases as $name => [$override, $field]) {
            try {
                $this->post('/admin/varian', $this->payload($override))->assertSessionHasErrors($field);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail("Kasus '$name' harus menolak field '$field': ".$e->getMessage());
            }
        }

        $this->assertDatabaseCount('variants', 0);
    }

    public function test_a_product_cannot_have_two_variants_with_the_same_number_of_months(): void
    {
        $product = Product::factory()->create(['name' => 'Netflix']);
        $existing = Variant::factory()->months(3, '3 Bulan Reguler')->create();
        ProductVariant::factory()->fromProduct()->create(['product_id' => $product->id, 'variant_id' => $existing->id]);

        $this->post('/admin/varian', $this->payload(['title' => '3 Bulan Hemat', 'months' => 3, 'products' => [$this->row($product)]]))
            ->assertSessionHasErrors(['products.0.product_id' => 'Netflix sudah punya varian 3 bulan ("3 Bulan Reguler").']);
        $this->assertDatabaseCount('variants', 1);

        // Jumlah bulan lain atau produk lain tidak bentrok.
        $other = Product::factory()->create();
        $this->post('/admin/varian', $this->payload(['title' => '6 Bulan', 'months' => 6, 'products' => [$this->row($product)]]))->assertSessionHasNoErrors();
        $this->post('/admin/varian', $this->payload(['title' => '3 Bulan B', 'months' => 3, 'products' => [$this->row($other)]]))->assertSessionHasNoErrors();
    }

    // ---------- ubah ----------

    public function test_update_changes_fields_and_syncs_products(): void
    {
        [$keep, $drop, $add] = Product::factory()->count(3)->create()->all();
        $variant = Variant::factory()->months(2)->create();
        ProductVariant::factory()->fromProduct()->create(['product_id' => $keep->id, 'variant_id' => $variant->id]);
        $dropped = ProductVariant::factory()->create(['product_id' => $drop->id, 'variant_id' => $variant->id]);

        $this->put("/admin/varian/{$variant->id}", $this->payload([
            'title' => '2 Bulan Promo', 'months' => 2, 'is_active' => false,
            'products' => [$this->row($keep, 'custom', 70000, 5), $this->row($add)],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $variant->refresh();
        $this->assertSame(['2 Bulan Promo', false], [$variant->title, $variant->is_active]);
        $this->assertSame([$keep->id, $add->id], $variant->productVariants()->orderBy('product_id')->pluck('product_id')->all());
        $this->assertDatabaseMissing('product_variants', ['id' => $dropped->id]);

        $kept = $variant->productVariants()->where('product_id', $keep->id)->first();
        $this->assertSame([PriceMode::Custom, 70000, 5], [$kept->price_mode, $kept->price, $kept->discount_percent]);
    }

    public function test_switching_a_row_back_to_from_product_clears_its_own_price(): void
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->months(2)->create();
        ProductVariant::factory()->create(['product_id' => $product->id, 'variant_id' => $variant->id, 'price' => 90000, 'discount_percent' => 10]);

        $this->put("/admin/varian/{$variant->id}", $this->payload(['months' => 2, 'products' => [$this->row($product)]]))->assertSessionHasNoErrors();

        $row = ProductVariant::firstOrFail();
        $this->assertSame([PriceMode::FromProduct, null, 0], [$row->price_mode, $row->price, $row->discount_percent]);
    }

    public function test_update_can_keep_its_own_months_but_not_clash_with_another_variant(): void
    {
        $product = Product::factory()->create();
        $mine = Variant::factory()->months(3)->create();
        ProductVariant::factory()->fromProduct()->create(['product_id' => $product->id, 'variant_id' => $mine->id]);
        $other = Variant::factory()->months(6, '6 Bulan')->create();
        ProductVariant::factory()->fromProduct()->create(['product_id' => $product->id, 'variant_id' => $other->id]);

        // Menyimpan ulang dengan bulan yang sama tidak dianggap bentrok dengan dirinya sendiri.
        $this->put("/admin/varian/{$mine->id}", $this->payload(['products' => [$this->row($product)]]))->assertSessionHasNoErrors();

        // Mengubah ke 6 bulan bentrok dengan varian lain di produk yang sama.
        $this->put("/admin/varian/{$mine->id}", $this->payload(['months' => 6, 'products' => [$this->row($product)]]))
            ->assertSessionHasErrors('products.0.product_id');
        $this->assertSame(3, $mine->fresh()->months);
    }

    public function test_toggle_flips_the_active_flag(): void
    {
        $variant = Variant::factory()->create(['is_active' => true]);

        $this->patch("/admin/varian/{$variant->id}/aktif")->assertSessionHas('success');
        $this->assertFalse($variant->fresh()->is_active);

        $this->patch("/admin/varian/{$variant->id}/aktif");
        $this->assertTrue($variant->fresh()->is_active);
    }

    public function test_destroy_removes_the_variant_and_its_product_links_only(): void
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->create();
        ProductVariant::factory()->fromProduct()->create(['product_id' => $product->id, 'variant_id' => $variant->id]);

        $this->delete("/admin/varian/{$variant->id}")->assertSessionHas('success');

        $this->assertDatabaseMissing('variants', ['id' => $variant->id]);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertNotNull($product->fresh(), 'produk tidak ikut terhapus');
    }

    // ---------- model ----------

    public function test_offers_are_ordered_by_months_and_can_be_limited_to_active_variants(): void
    {
        $product = Product::factory()->create(['price' => 10000, 'discount_percent' => 0]);
        foreach ([6 => true, 1 => true, 3 => false] as $months => $active) {
            $variant = Variant::factory()->months($months)->create(['is_active' => $active]);
            ProductVariant::factory()->fromProduct()->create(['product_id' => $product->id, 'variant_id' => $variant->id]);
        }

        $product = $product->fresh('productVariants.variant');

        $this->assertSame([1, 3, 6], $product->offers()->map(fn ($o) => $o->variant->months)->all());
        $this->assertSame([1, 6], $product->offers(onlyActive: true)->map(fn ($o) => $o->variant->months)->all());
        $this->assertSame([10000, 30000, 60000], $product->offers()->pluck('final_price')->all());
    }
}
