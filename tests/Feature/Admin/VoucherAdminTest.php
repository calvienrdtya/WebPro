<?php

namespace Tests\Feature\Admin;

use App\Enums\DiscountType;
use App\Models\Product;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherAdminTest extends TestCase
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
            'code' => 'HEMAT50',
            'title' => 'Diskon 50%',
            'description' => null,
            'discount_type' => 'percent',
            'discount_value' => 50,
            'max_discount' => null,
            'min_purchase' => null,
            'starts_on' => null,
            'ends_on' => null,
            'quota' => null,
            'per_user_limit' => 1,
            'requires_claim' => true,
            'is_listed' => true,
            'is_active' => true,
            'applies_to_all' => true,
            'product_ids' => [],
        ], $overrides);
    }

    // ---------- akses ----------

    public function test_guests_and_regular_users_cannot_reach_voucher_routes(): void
    {
        $voucher = Voucher::factory()->create();
        $routes = [
            ['get', '/admin/voucher'], ['post', '/admin/voucher'], ['put', "/admin/voucher/{$voucher->id}"],
            ['patch', "/admin/voucher/{$voucher->id}/aktif"], ['delete', "/admin/voucher/{$voucher->id}"],
            ['patch', '/admin/voucher/massal/aktif'], ['delete', '/admin/voucher/massal'],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertNotNull($voucher->fresh());
    }

    // ---------- daftar ----------

    public function test_index_lists_vouchers_with_status_counts_and_claims(): void
    {
        $running = Voucher::factory()->create(['code' => 'JALAN']);
        Voucher::factory()->create(['code' => 'NANTI', 'starts_on' => today()->addDay()]);
        Voucher::factory()->create(['code' => 'LEWAT', 'ends_on' => today()->subDay()]);
        Voucher::factory()->create(['code' => 'HABIS', 'quota' => 5, 'used_count' => 5]);
        Voucher::factory()->inactive()->create(['code' => 'MATI']);
        UserVoucher::create(['user_id' => User::factory()->create()->id, 'voucher_id' => $running->id, 'claimed_at' => now()]);

        $this->get('/admin/voucher')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Vouchers/Index')
            ->has('vouchers.data', 5)
            ->where('counts', ['all' => 5, 'running' => 1, 'scheduled' => 1, 'ended' => 2, 'inactive' => 1])
            ->where('stats.claims', 1)
            ->where('stats.used', 5));

        $this->get('/admin/voucher?status=ended')->assertInertia(fn ($page) => $page
            ->has('vouchers.data', 2));
        $this->get('/admin/voucher?status=running')->assertInertia(fn ($page) => $page
            ->has('vouchers.data', 1)->where('vouchers.data.0.code', 'JALAN')->where('vouchers.data.0.claims_count', 1)
            ->where('vouchers.data.0.status', 'running'));
    }

    public function test_index_search_matches_code_case_insensitively_and_title(): void
    {
        Voucher::factory()->create(['code' => 'ABC123', 'title' => 'Satu']);
        Voucher::factory()->create(['code' => 'XYZ', 'title' => 'Promo Lebaran']);

        $this->get('/admin/voucher?q=abc')->assertInertia(fn ($page) => $page->has('vouchers.data', 1)->where('vouchers.data.0.code', 'ABC123'));
        $this->get('/admin/voucher?q=lebaran')->assertInertia(fn ($page) => $page->has('vouchers.data', 1)->where('vouchers.data.0.code', 'XYZ'));
    }

    // ---------- tambah / ubah ----------

    public function test_store_normalizes_the_code_and_attaches_products(): void
    {
        [$a, $b] = Product::factory()->count(2)->create()->all();

        $this->post('/admin/voucher', $this->payload([
            'code' => ' hemat 50 ', 'applies_to_all' => false, 'product_ids' => [$a->id, $b->id], 'min_purchase' => '050000',
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $voucher = Voucher::firstOrFail();
        $this->assertSame('HEMAT50', $voucher->code);
        $this->assertSame(50000, $voucher->min_purchase);
        $this->assertFalse($voucher->applies_to_all);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $voucher->products()->pluck('products.id')->all());
    }

    public function test_applies_to_all_stores_no_product_list_and_update_clears_it(): void
    {
        $product = Product::factory()->create();
        $this->post('/admin/voucher', $this->payload(['applies_to_all' => false, 'product_ids' => [$product->id]]))->assertSessionHasNoErrors();
        $voucher = Voucher::firstOrFail();
        $this->assertCount(1, $voucher->products);

        $this->put("/admin/voucher/{$voucher->id}", $this->payload(['applies_to_all' => true, 'product_ids' => [$product->id]]))->assertSessionHasNoErrors();
        $this->assertCount(0, $voucher->fresh()->products);
    }

    public function test_max_discount_is_dropped_for_fixed_vouchers(): void
    {
        $this->post('/admin/voucher', $this->payload(['discount_type' => 'fixed', 'discount_value' => 25000, 'max_discount' => 1000]))->assertSessionHasNoErrors();

        $voucher = Voucher::firstOrFail();
        $this->assertSame(DiscountType::Fixed, $voucher->discount_type);
        $this->assertNull($voucher->max_discount);
    }

    public function test_update_keeps_the_same_code_and_cannot_lower_quota_below_used_count(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'HEMAT50', 'quota' => 10, 'used_count' => 4]);

        $this->put("/admin/voucher/{$voucher->id}", $this->payload(['quota' => 3]))->assertSessionHasErrors('quota');
        $this->put("/admin/voucher/{$voucher->id}", $this->payload(['quota' => 4, 'title' => 'Baru']))->assertSessionHasNoErrors();

        $this->assertSame(['Baru', 4, 4], [$voucher->fresh()->title, $voucher->fresh()->quota, $voucher->fresh()->used_count]);
    }

    public function test_store_validation_rules(): void
    {
        Voucher::factory()->create(['code' => 'SUDAHADA']);

        $cases = [
            'kode kosong' => [['code' => ''], 'code'],
            'kode terlalu pendek' => [['code' => 'AB'], 'code'],
            'kode terlalu panjang' => [['code' => str_repeat('A', 31)], 'code'],
            'kode berkarakter aneh' => [['code' => 'HE$MAT'], 'code'],
            'kode kembar (beda huruf)' => [['code' => 'sudahada'], 'code'],
            'judul kosong' => [['title' => ''], 'title'],
            'jenis tidak dikenal' => [['discount_type' => 'cashback'], 'discount_type'],
            'nilai kosong' => [['discount_value' => null], 'discount_value'],
            'nilai nol' => [['discount_value' => 0], 'discount_value'],
            'persen lebih dari 100' => [['discount_value' => 101], 'discount_value'],
            'maks potongan nol' => [['max_discount' => 0], 'max_discount'],
            'minimal pembelian nol' => [['min_purchase' => 0], 'min_purchase'],
            'tanggal akhir sebelum mulai' => [['starts_on' => '2026-10-10', 'ends_on' => '2026-10-09'], 'ends_on'],
            'format tanggal salah' => [['starts_on' => '10/10/2026'], 'starts_on'],
            'kuota nol' => [['quota' => 0], 'quota'],
            'batas per user nol' => [['per_user_limit' => 0], 'per_user_limit'],
            'produk dipilih tapi kosong' => [['applies_to_all' => false, 'product_ids' => []], 'product_ids'],
            'produk tidak ada' => [['applies_to_all' => false, 'product_ids' => [9999]], 'product_ids.0'],
        ];
        // max_discount hanya dicek untuk persen (default payload), jadi kasus ini valid di jalur persen.

        foreach ($cases as $name => [$override, $field]) {
            $this->post('/admin/voucher', $this->payload($override))->assertSessionHasErrors($field, $name);
        }
        $this->assertDatabaseCount('vouchers', 1);
    }

    public function test_fixed_amount_may_exceed_one_hundred_and_dates_may_be_equal(): void
    {
        $this->post('/admin/voucher', $this->payload([
            'discount_type' => 'fixed', 'discount_value' => 25000, 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-10', 'per_user_limit' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Voucher::firstOrFail()->per_user_limit);
    }

    // ---------- aktif / nonaktif ----------

    public function test_toggle_flips_active_status(): void
    {
        $voucher = Voucher::factory()->create();

        $this->patch("/admin/voucher/{$voucher->id}/aktif")->assertSessionHas('success');
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertSame('inactive', $voucher->fresh()->status());

        $this->patch("/admin/voucher/{$voucher->id}/aktif");
        $this->assertTrue($voucher->fresh()->is_active);
    }

    public function test_bulk_activate_deactivate_and_delete(): void
    {
        [$a, $b, $c] = Voucher::factory()->count(3)->create()->all();

        $this->patch('/admin/voucher/massal/aktif', ['ids' => [$a->id, $b->id], 'active' => false])->assertSessionHasNoErrors();
        $this->assertSame([false, false, true], [$a->fresh()->is_active, $b->fresh()->is_active, $c->fresh()->is_active]);

        $this->patch('/admin/voucher/massal/aktif', ['ids' => [$a->id], 'active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->is_active);

        $this->patch('/admin/voucher/massal/aktif', ['ids' => [$a->id]])->assertSessionHasErrors('active');
        $this->patch('/admin/voucher/massal/aktif', ['ids' => [9999], 'active' => true])->assertSessionHasErrors('ids.0');

        $this->delete('/admin/voucher/massal', ['ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('vouchers', 1);
    }

    // ---------- hapus ----------

    public function test_destroy_removes_voucher_with_its_claims_and_products(): void
    {
        $voucher = Voucher::factory()->create(['applies_to_all' => false]);
        $voucher->products()->sync([Product::factory()->create()->id]);
        UserVoucher::create(['user_id' => User::factory()->create()->id, 'voucher_id' => $voucher->id, 'claimed_at' => now()]);

        $this->delete("/admin/voucher/{$voucher->id}")->assertSessionHas('success');

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('user_vouchers', 0);
        $this->assertDatabaseCount('voucher_product', 0);
    }

    // ---------- perhitungan potongan ----------

    public function test_discount_for_percent_fixed_cap_and_minimum(): void
    {
        $percent = Voucher::factory()->make(['discount_value' => 50, 'max_discount' => 20000]);
        $this->assertSame(20000, $percent->discountFor(100000)); // dibatasi plafon
        $this->assertSame(15000, $percent->discountFor(30000));
        $this->assertSame(0, $percent->discountFor(0));

        $fixed = Voucher::factory()->fixed(25000)->make();
        $this->assertSame(25000, $fixed->discountFor(100000));
        $this->assertSame(10000, $fixed->discountFor(10000)); // tidak melebihi subtotal

        $min = Voucher::factory()->make(['discount_value' => 10, 'min_purchase' => 50000]);
        $this->assertSame(0, $min->discountFor(49999));
        $this->assertSame(5000, $min->discountFor(50000));

        $this->assertSame(3333, Voucher::factory()->make(['discount_value' => 33])->discountFor(10101)); // pembulatan ke bawah
    }

    public function test_applicable_to_scope_matches_all_or_attached_products(): void
    {
        [$netflix, $disney] = Product::factory()->count(2)->create()->all();
        $all = Voucher::factory()->create();
        $only = Voucher::factory()->create(['applies_to_all' => false]);
        $only->products()->sync([$netflix->id]);

        $this->assertEqualsCanonicalizing([$all->id, $only->id], Voucher::applicableTo($netflix)->pluck('id')->all());
        $this->assertSame([$all->id], Voucher::applicableTo($disney)->pluck('id')->all());
    }
}
