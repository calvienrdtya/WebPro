<?php

namespace Tests\Feature\Store;

use App\Enums\PriceMode;
use App\Models\Account;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ServiceFee;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Variant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOrderFormTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::factory()->create(['name' => 'Netflix Premium', 'slug' => 'netflix', 'price' => 100000, 'discount_percent' => 50]); // 50.000
        $v3 = Variant::factory()->create(['months' => 3, 'title' => '3 Bulan']);
        $v1 = Variant::factory()->create(['months' => 1, 'title' => '1 Bulan']);
        ProductVariant::create(['product_id' => $this->product->id, 'variant_id' => $v1->id, 'price_mode' => PriceMode::FromProduct]);
        ProductVariant::create(['product_id' => $this->product->id, 'variant_id' => $v3->id, 'price_mode' => PriceMode::Custom, 'price' => 300000, 'discount_percent' => 50]); // 150.000
    }

    private function check(string $code, int $option = 0)
    {
        return $this->postJson('/produk/netflix/voucher', ['code' => $code, 'option' => $option]);
    }

    // ---------- form di halaman ----------

    public function test_product_page_has_the_order_form_voucher_box_and_price_breakdown(): void
    {
        Account::factory()->create(['product_id' => $this->product->id]);

        $this->get('/produk/netflix')->assertOk()
            ->assertSee('Isi form order')->assertSee('Nama lengkap')->assertSee('Nomor WhatsApp')
            ->assertSee('Kode voucher (opsional)')->assertSee('Voucher yang Bisa Dipakai')
            ->assertSee('Rincian harga')->assertSee('Beli Sekarang')
            ->assertDontSee('Biaya layanan')
            ->assertSee('Belum ada voucher tersedia')->assertSee('Masuk dan klaim voucher agar muncul di sini');
    }

    public function test_buy_button_is_disabled_and_labelled_when_out_of_stock(): void
    {
        $this->get('/produk/netflix')->assertOk()->assertSee('Stok habis')->assertDontSee('Beli Sekarang');
    }

    public function test_form_is_prefilled_for_a_logged_in_user_only(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@uji.test', 'phone' => '081234567890']);

        $this->actingAs($user)->get('/produk/netflix')->assertOk()
            ->assertSee('Budi Santoso')->assertSee('budi@uji.test')->assertSee('081234567890');

        auth()->logout();
        $this->get('/produk/netflix')->assertOk()->assertDontSee('budi@uji.test');
    }

    public function test_service_fee_row_appears_only_when_a_fee_is_active(): void
    {
        $this->get('/produk/netflix')->assertDontSee('Biaya layanan');

        $fee = ServiceFee::create(['name' => 'Biaya Admin', 'amount' => 1500, 'is_active' => true]);
        $this->get('/produk/netflix')->assertSee('Biaya layanan')->assertSee('Rp 1.500', false);

        $fee->update(['is_active' => false]);
        $this->get('/produk/netflix')->assertDontSee('Biaya layanan');
    }

    public function test_voucher_choices_list_only_usable_vouchers(): void
    {
        $user = User::factory()->create();
        $mine = Voucher::factory()->create(['code' => 'DIKLAIMKU']);
        UserVoucher::create(['user_id' => $user->id, 'voucher_id' => $mine->id, 'claimed_at' => now()]);
        Voucher::factory()->create(['code' => 'BELUMDIKLAIM']);
        Voucher::factory()->create(['code' => 'LANGSUNG', 'requires_claim' => false]);
        Voucher::factory()->create(['code' => 'TIDAKTAMPIL', 'requires_claim' => false, 'is_listed' => false]);
        Voucher::factory()->create(['code' => 'PRODUKLAIN', 'requires_claim' => false, 'applies_to_all' => false])->products()->sync([Product::factory()->create()->id]);
        Voucher::factory()->create(['code' => 'SUDAHLEWAT', 'requires_claim' => false, 'ends_on' => today()->subDay()]);

        $this->get('/produk/netflix')->assertOk()->assertSee('LANGSUNG')
            ->assertDontSee('DIKLAIMKU')->assertDontSee('BELUMDIKLAIM')->assertDontSee('TIDAKTAMPIL')->assertDontSee('PRODUKLAIN')->assertDontSee('SUDAHLEWAT');

        $this->actingAs($user)->get('/produk/netflix')->assertOk()->assertSee('DIKLAIMKU')->assertSee('LANGSUNG')->assertDontSee('BELUMDIKLAIM');
    }

    // ---------- cek voucher ----------

    public function test_valid_direct_voucher_returns_the_server_computed_discount_for_the_chosen_duration(): void
    {
        Voucher::factory()->create(['code' => 'HEMAT10', 'discount_value' => 10, 'requires_claim' => false]);

        $this->check('hemat10')->assertOk()->assertJson(['ok' => true, 'discount' => 5000, 'code' => 'HEMAT10']);
        $this->check('HEMAT10', 1)->assertJson(['ok' => true, 'discount' => 15000]); // 3 bulan: 150.000 x 10%
    }

    public function test_discount_respects_cap_fixed_amount_and_never_exceeds_the_price(): void
    {
        Voucher::factory()->create(['code' => 'CAPPED', 'discount_value' => 50, 'max_discount' => 10000, 'requires_claim' => false]);
        Voucher::factory()->fixed(80000)->create(['code' => 'BESAR', 'requires_claim' => false]);

        $this->check('CAPPED', 1)->assertJson(['ok' => true, 'discount' => 10000]);
        $this->check('BESAR')->assertJson(['ok' => true, 'discount' => 50000]); // tidak melebihi harga produk
    }

    public function test_invalid_unknown_ended_inactive_and_foreign_vouchers_are_rejected(): void
    {
        Voucher::factory()->create(['code' => 'LEWAT', 'requires_claim' => false, 'ends_on' => today()->subDay()]);
        Voucher::factory()->inactive()->create(['code' => 'MATI', 'requires_claim' => false]);
        Voucher::factory()->create(['code' => 'LAIN', 'requires_claim' => false, 'applies_to_all' => false])->products()->sync([Product::factory()->create()->id]);

        $this->check('NGAWUR')->assertOk()->assertJson(['ok' => false, 'discount' => 0, 'message' => 'Kode voucher tidak valid atau sudah berakhir.']);
        $this->check('LEWAT')->assertJson(['ok' => false]);
        $this->check('MATI')->assertJson(['ok' => false]);
        $this->check('LAIN')->assertJson(['ok' => false, 'message' => 'Voucher ini tidak berlaku untuk produk ini.']);
    }

    public function test_minimum_purchase_is_checked_against_the_product_price_of_the_chosen_duration(): void
    {
        Voucher::factory()->create(['code' => 'MIN100', 'min_purchase' => 100000, 'discount_value' => 10, 'requires_claim' => false]);

        $this->check('MIN100', 0)->assertJson(['ok' => false, 'message' => 'Minimal pembelian Rp100.000 untuk memakai voucher ini.']);
        $this->check('MIN100', 1)->assertJson(['ok' => true, 'discount' => 15000]);
    }

    public function test_claim_required_voucher_needs_login_and_a_claim(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'PERLUKLAIM']);

        $this->check('PERLUKLAIM')->assertJson(['ok' => false, 'message' => 'Masuk dan klaim voucher ini dulu di halaman Voucher.']);

        $user = User::factory()->create();
        $this->actingAs($user)->check('PERLUKLAIM')->assertJson(['ok' => false, 'message' => 'Voucher ini perlu diklaim dulu di halaman Voucher.']);

        UserVoucher::create(['user_id' => $user->id, 'voucher_id' => $voucher->id, 'claimed_at' => now()]);
        $this->check('PERLUKLAIM')->assertJson(['ok' => true, 'discount' => 5000]);
    }

    public function test_unlisted_voucher_can_still_be_used_by_typing_its_code(): void
    {
        Voucher::factory()->create(['code' => 'RAHASIA', 'requires_claim' => false, 'is_listed' => false]);

        $this->check('RAHASIA')->assertJson(['ok' => true]);
    }

    public function test_check_validates_input_and_product(): void
    {
        $this->postJson('/produk/netflix/voucher', [])->assertStatus(422)->assertJsonValidationErrors(['code', 'option']);
        $this->postJson('/produk/netflix/voucher', ['code' => 'X', 'option' => 9])->assertStatus(422)->assertJsonValidationErrors('option');
        $this->postJson('/produk/tidak-ada/voucher', ['code' => 'X', 'option' => 0])->assertNotFound();
    }
}
