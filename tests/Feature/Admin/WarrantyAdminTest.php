<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use App\Models\Warranty;
use Database\Seeders\WarrantySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyAdminTest extends TestCase
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
            'name' => 'Garansi 7 Hari',
            'applies_to_all' => false,
            'price' => 5000,
            'duration_days' => 7,
            'is_active' => true,
            'product_ids' => [],
        ], $overrides);
    }

    // ---------- akses ----------

    public function test_guests_and_regular_users_cannot_reach_warranty_routes(): void
    {
        $warranty = Warranty::factory()->create();
        $routes = [
            ['get', '/admin/garansi'], ['post', '/admin/garansi'], ['put', "/admin/garansi/{$warranty->id}"],
            ['patch', "/admin/garansi/{$warranty->id}/aktif"], ['delete', "/admin/garansi/{$warranty->id}"],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertNotNull($warranty->fresh());
    }

    // ---------- daftar ----------

    public function test_index_lists_warranties_by_price_with_their_products(): void
    {
        [$netflix, $disney] = [Product::factory()->create(['name' => 'Netflix']), Product::factory()->create(['name' => 'Disney'])];
        $mahal = Warranty::factory()->create(['name' => 'Penuh', 'price' => 10000]);
        $murah = Warranty::factory()->create(['name' => 'Singkat', 'price' => 3000]);
        $mahal->products()->sync([$netflix->id, $disney->id]);

        $this->get('/admin/garansi')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Warranties/Index')
            ->where('warranties.0.id', $murah->id)
            ->where('warranties.0.products', [])
            ->where('warranties.1.id', $mahal->id)
            ->where('warranties.1.products.0.name', 'Disney')
            ->where('warranties.1.products.1.name', 'Netflix')
            ->has('products', 2));
    }

    // ---------- tambah ----------

    public function test_store_creates_a_warranty_and_attaches_products(): void
    {
        [$a, $b] = Product::factory()->count(2)->create()->all();

        $this->post('/admin/garansi', $this->payload(['product_ids' => [$a->id, $b->id]]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $warranty = Warranty::firstOrFail();
        $this->assertSame(['Garansi 7 Hari', 5000, 7, true], [$warranty->name, $warranty->price, $warranty->duration_days, $warranty->is_active]);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $warranty->products()->pluck('products.id')->all());
    }

    public function test_a_free_warranty_without_products_is_allowed(): void
    {
        $this->post('/admin/garansi', $this->payload(['price' => 0, 'product_ids' => null]))->assertSessionHasNoErrors();

        $warranty = Warranty::firstOrFail();
        $this->assertSame(0, $warranty->price);
        $this->assertCount(0, $warranty->products);
    }

    public function test_descriptions_no_longer_exist(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('warranties', 'descriptions'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('warranties', 'description'));

        // Field lama yang terkirim diabaikan, tidak membuat galat.
        $this->post('/admin/garansi', $this->payload(['descriptions' => ['abaikan saja']]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('warranties', 1);
    }

    public function test_numbers_typed_with_leading_zeros_are_accepted(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/garansi', $this->payload(['price' => '005000', 'duration_days' => '07', 'product_ids' => [(string) $product->id]]))->assertSessionHasNoErrors();

        $warranty = Warranty::firstOrFail();
        $this->assertSame([5000, 7], [$warranty->price, $warranty->duration_days]);
        $this->assertCount(1, $warranty->products);
    }

    public function test_store_validation_rules(): void
    {
        $product = Product::factory()->create();
        $gone = Product::factory()->create();
        $gone->delete();
        Warranty::factory()->create(['name' => 'Sudah Ada']);

        $cases = [
            'nama kosong' => [['name' => ''], 'name'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 61)], 'name'],
            'nama kembar' => [['name' => 'Sudah Ada'], 'name'],
            'harga kosong' => [['price' => null], 'price'],
            'harga negatif' => [['price' => -1], 'price'],
            'harga bukan angka' => [['price' => 'murah'], 'price'],
            'masa kosong' => [['duration_days' => null], 'duration_days'],
            'masa nol' => [['duration_days' => 0], 'duration_days'],
            'masa terlalu lama' => [['duration_days' => 3651], 'duration_days'],
            'produk tidak ada' => [['product_ids' => [9999]], 'product_ids.0'],
            'produk sudah dihapus' => [['product_ids' => [$gone->id]], 'product_ids.0'],
            'produk kembar' => [['product_ids' => [$product->id, $product->id]], 'product_ids.1'],
            'produk bukan array' => [['product_ids' => 'semua'], 'product_ids'],
        ];

        foreach ($cases as $name => [$override, $field]) {
            try {
                $this->post('/admin/garansi', $this->payload($override))->assertSessionHasErrors($field);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail("Kasus '$name' harus menolak field '$field': ".$e->getMessage());
            }
        }

        $this->assertDatabaseCount('warranties', 1);
    }

    // ---------- ubah ----------

    public function test_update_changes_fields_and_syncs_products(): void
    {
        [$keep, $drop, $add] = Product::factory()->count(3)->create()->all();
        $warranty = Warranty::factory()->create(['name' => 'Lama']);
        $warranty->products()->sync([$keep->id, $drop->id]);

        $this->put("/admin/garansi/{$warranty->id}", $this->payload([
            'name' => 'Baru', 'price' => 12000, 'duration_days' => 30, 'is_active' => false, 'product_ids' => [$keep->id, $add->id],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $warranty->refresh();
        $this->assertSame(['Baru', 12000, 30, false], [$warranty->name, $warranty->price, $warranty->duration_days, $warranty->is_active]);
        $this->assertEqualsCanonicalizing([$keep->id, $add->id], $warranty->products()->pluck('products.id')->all());
    }

    public function test_update_can_keep_its_own_name_but_not_take_another_warranties(): void
    {
        $mine = Warranty::factory()->create(['name' => 'Milik Saya']);
        Warranty::factory()->create(['name' => 'Milik Lain']);

        $this->put("/admin/garansi/{$mine->id}", $this->payload(['name' => 'Milik Saya']))->assertSessionHasNoErrors();
        $this->put("/admin/garansi/{$mine->id}", $this->payload(['name' => 'Milik Lain']))->assertSessionHasErrors(['name' => 'Sudah ada garansi dengan nama itu.']);
        $this->assertSame('Milik Saya', $mine->fresh()->name);
    }

    public function test_sending_no_products_on_update_detaches_all_of_them(): void
    {
        $warranty = Warranty::factory()->create();
        $warranty->products()->sync(Product::factory()->count(2)->create()->pluck('id'));

        $this->put("/admin/garansi/{$warranty->id}", $this->payload(['name' => $warranty->name, 'product_ids' => []]))->assertSessionHasNoErrors();

        $this->assertCount(0, $warranty->products()->get());
    }

    public function test_toggle_flips_the_active_flag(): void
    {
        $warranty = Warranty::factory()->create(['is_active' => true]);

        $this->patch("/admin/garansi/{$warranty->id}/aktif")->assertSessionHas('success');
        $this->assertFalse($warranty->fresh()->is_active);

        $this->patch("/admin/garansi/{$warranty->id}/aktif");
        $this->assertTrue($warranty->fresh()->is_active);
    }

    public function test_destroy_removes_the_warranty_and_its_links_but_not_the_products(): void
    {
        $product = Product::factory()->create();
        $warranty = Warranty::factory()->create();
        $warranty->products()->sync([$product->id]);

        $this->delete("/admin/garansi/{$warranty->id}")->assertSessionHas('success');

        $this->assertDatabaseMissing('warranties', ['id' => $warranty->id]);
        $this->assertDatabaseCount('product_warranty', 0);
        $this->assertNotNull($product->fresh());
    }

    // ---------- produk & seeder ----------

    public function test_product_edit_page_summarises_its_warranties(): void
    {
        $product = Product::factory()->create();
        $penuh = Warranty::factory()->create(['name' => 'Penuh', 'price' => 10000, 'duration_days' => 28]);
        $singkat = Warranty::factory()->inactive()->create(['name' => 'Singkat', 'price' => 0, 'duration_days' => 3]);
        Warranty::factory()->create(['name' => 'Tidak Terpasang']);
        $semua = Warranty::factory()->forAllProducts()->create(['name' => 'Untuk Semua', 'price' => 7000]);
        $product->warranties()->sync([$penuh->id, $singkat->id]);

        $this->get("/admin/produk/{$product->id}/ubah")->assertInertia(fn ($page) => $page
            ->has('product.warranties', 3)
            ->where('product.warranties.0.name', 'Singkat')
            ->where('product.warranties.0.is_active', false)
            ->where('product.warranties.1.name', 'Untuk Semua')
            ->where('product.warranties.2.price', 10000));
    }

    public function test_applies_to_all_ignores_the_product_list_and_clears_old_links(): void
    {
        [$a, $b] = Product::factory()->count(2)->create()->all();
        $warranty = Warranty::factory()->create();
        $warranty->products()->sync([$a->id]);

        $this->put("/admin/garansi/{$warranty->id}", $this->payload(['name' => $warranty->name, 'applies_to_all' => true, 'product_ids' => [$a->id, $b->id]]))->assertSessionHasNoErrors();

        $warranty->refresh();
        $this->assertTrue($warranty->applies_to_all);
        $this->assertCount(0, $warranty->products()->get(), 'daftar produk tidak disimpan untuk garansi semua produk');

        $this->get('/admin/garansi')->assertInertia(fn ($page) => $page->where('warranties.0.applies_to_all', true)->where('warranties.0.products', []));
    }

    public function test_an_all_products_warranty_applies_to_products_created_later(): void
    {
        $all = Warranty::factory()->forAllProducts()->create(['name' => 'Semua']);
        $some = Warranty::factory()->create(['name' => 'Sebagian']);
        $old = Product::factory()->create();
        $some->products()->sync([$old->id]);

        $newProduct = Product::factory()->create();

        $names = fn (Product $p) => Warranty::applicableTo($p)->orderBy('id')->pluck('name')->all();
        $this->assertSame(['Semua', 'Sebagian'], $names($old));
        $this->assertSame(['Semua'], $names($newProduct), 'produk baru langsung mendapat garansi semua produk');
        $this->assertSame(['Semua'], Warranty::applicableTo($newProduct->id)->pluck('name')->all());
    }

    public function test_seeder_creates_two_all_product_packages_and_is_safe_to_run_twice(): void
    {
        $this->seed(WarrantySeeder::class);
        Warranty::where('name', 'Garansi 7 Hari')->update(['price' => 1234]);
        $this->seed(WarrantySeeder::class);

        $this->assertSame(2, Warranty::count());
        $this->assertSame(1234, Warranty::where('name', 'Garansi 7 Hari')->value('price'), 'edit admin tidak ditimpa');
        $this->assertSame([true, true], Warranty::orderBy('id')->pluck('applies_to_all')->all());
    }
}
