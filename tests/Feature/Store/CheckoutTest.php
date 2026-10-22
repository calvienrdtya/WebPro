<?php

namespace Tests\Feature\Store;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\PriceMode;
use App\Models\Account;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ServiceFee;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Variant;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        $this->product = Product::factory()->create(['name' => 'Netflix Premium', 'slug' => 'netflix', 'price' => 100000, 'discount_percent' => 50]); // 50.000
        $v1 = Variant::factory()->create(['months' => 1, 'title' => '1 Bulan']);
        $v3 = Variant::factory()->create(['months' => 3, 'title' => '3 Bulan']);
        ProductVariant::create(['product_id' => $this->product->id, 'variant_id' => $v1->id, 'price_mode' => PriceMode::FromProduct]);
        ProductVariant::create(['product_id' => $this->product->id, 'variant_id' => $v3->id, 'price_mode' => PriceMode::Custom, 'price' => 300000, 'discount_percent' => 50]); // 150.000
        $this->user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@uji.test']);
    }

    private function stock(int $count = 1): void
    {
        Account::factory()->count($count)->create(['product_id' => $this->product->id, 'cogs' => 12000]);
    }

    private function payload(array $override = []): array
    {
        return array_merge(['name' => 'Budi Santoso', 'email' => 'budi@uji.test', 'wa' => '081234567890', 'option' => 0], $override);
    }

    private function buy(array $override = [])
    {
        return $this->actingAs($this->user)->post('/produk/netflix/beli', $this->payload($override));
    }

    // ---------- pembuatan pesanan ----------

    public function test_guest_is_sent_to_login_and_returns_to_the_product_page(): void
    {
        $this->post('/produk/netflix/beli', $this->payload())->assertRedirect(route('login'));
        $this->get('/produk/netflix/beli')->assertRedirect(route('login'));
        $this->assertSame(0, Order::count());

        $this->actingAs($this->user)->get('/produk/netflix/beli')->assertRedirect(route('products.show', 'netflix'));
    }

    public function test_placing_an_order_snapshots_prices_and_links_no_account_until_confirmed(): void
    {
        $this->stock(2);
        ServiceFee::create(['name' => 'Biaya Admin', 'amount' => 1000, 'is_active' => true]);
        $warranty = Warranty::factory()->forAllProducts()->create(['name' => 'Garansi Penuh', 'price' => 10000, 'duration_days' => 28]);

        $response = $this->buy(['garansi' => $warranty->id]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertMatchesRegularExpression('/^PRO\d{6}[A-Z0-9]{5}$/', $order->code);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame($this->user->id, $order->user_id);
        $this->assertSame([100000, 50000, 10000, 1000, 61000], [$order->list_price, $order->product_price, $order->warranty_price, $order->service_fee, $order->total]);
        $this->assertSame(['Netflix Premium', '1 Bulan', 'Garansi Penuh', 28], [$order->product_name, $order->variant_title, $order->warranty_name, $order->warranty_days]);
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, $order->expires_at->timestamp, 5);

        $this->assertSame(2, Account::where('status', AccountStatus::Available)->count());
        $this->assertSame(1, $order->deliveries()->count());
        $this->assertNull($order->deliveries()->first()->account_id);
    }

    public function test_the_service_fee_is_copied_into_the_order_and_later_edits_do_not_change_it(): void
    {
        $this->stock();
        $fee = ServiceFee::create(['name' => 'Biaya Admin', 'amount' => 1000, 'is_active' => true]);

        $this->buy()->assertRedirect();
        $fee->update(['amount' => 5000]);

        $order = Order::firstOrFail();
        $this->assertSame([1000, 51000], [$order->service_fee, $order->total]);
    }

    public function test_a_product_specific_service_fee_overrides_the_active_fee_and_zero_means_free(): void
    {
        $this->stock(2);
        ServiceFee::create(['name' => 'Biaya Admin', 'amount' => 1000, 'is_active' => true]);

        $this->product->update(['service_fee' => 2500]);
        $this->get('/produk/netflix')->assertOk()->assertViewHas('serviceFee', 2500);
        $this->buy()->assertRedirect();
        $this->assertSame([2500, 52500], [Order::latest('id')->first()->service_fee, Order::latest('id')->first()->total]);

        $this->product->update(['service_fee' => 0]);
        $this->buy()->assertRedirect();
        $this->assertSame(0, Order::latest('id')->first()->service_fee);

        $this->product->update(['service_fee' => null]);
        $this->get('/produk/netflix')->assertViewHas('serviceFee', 1000);
    }

    public function test_multi_month_order_creates_one_delivery_per_month_without_accounts(): void
    {
        $this->stock(3);

        $this->buy(['option' => 1])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(3, $order->months);
        $this->assertSame(150000, $order->total);
        $this->assertSame([1, 2, 3], $order->deliveries()->pluck('sequence')->all());
        $this->assertSame([false, false, false], $order->deliveries->map(fn ($d) => $d->account_id !== null)->all());
        $this->assertSame(3, Account::where('status', AccountStatus::Available)->count());
    }

    public function test_a_warranty_is_required_when_the_product_has_one_and_it_must_apply_to_the_product(): void
    {
        $this->stock();
        $other = Product::factory()->create();
        $foreign = Warranty::factory()->create();
        $foreign->products()->sync([$other->id]);
        Warranty::factory()->forAllProducts()->create();

        $this->buy()->assertRedirect(route('products.show', 'netflix'))->assertSessionHas('error', 'Pilih salah satu garansi dulu.');
        $this->buy(['garansi' => $foreign->id])->assertSessionHas('error', 'Pilih salah satu garansi dulu.');
        $this->assertSame(0, Order::count());
    }

    public function test_a_product_without_warranties_can_be_ordered_without_one(): void
    {
        $this->stock();

        $this->buy()->assertRedirect();

        $this->assertNull(Order::firstOrFail()->warranty_id);
    }

    public function test_out_of_stock_fails_cleanly_and_expired_stock_does_not_count(): void
    {
        Account::factory()->create(['product_id' => $this->product->id, 'started_at' => today()->subDays(40)->toDateString(), 'active_days' => 28]);

        $this->buy()->assertRedirect(route('products.show', 'netflix'))->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    public function test_unpaid_orders_do_not_reduce_stock_so_others_can_still_order(): void
    {
        $this->stock(1);
        $second = User::factory()->create();

        $this->buy()->assertRedirect();
        $this->actingAs($second)->post('/produk/netflix/beli', $this->payload())->assertRedirect();

        $this->assertSame(2, Order::count());
        $this->assertSame(1, Account::where('status', AccountStatus::Available)->count());
    }

    public function test_invalid_input_and_inactive_products_are_rejected(): void
    {
        $this->stock();

        $this->buy(['name' => '', 'email' => 'bukan-email', 'wa' => 'abc'])->assertSessionHasErrors(['name', 'email', 'wa']);
        $this->buy(['option' => 9])->assertSessionHas('error', 'Pilihan durasi tidak valid.');
        $this->product->update(['is_active' => false]);
        $this->buy()->assertNotFound();
        $this->assertSame(0, Order::count());
    }

    public function test_a_user_cannot_hold_more_unpaid_orders_than_the_limit(): void
    {
        $this->stock(5);
        config(['proakses.max_open_orders' => 2]);

        $this->buy()->assertRedirect(route('orders.show', Order::query()->first() ?? 'x'));
        $this->buy();
        $this->buy()->assertSessionHas('error');

        $this->assertSame(2, Order::count());
    }

    // ---------- voucher ----------

    public function test_voucher_discount_is_applied_and_quota_is_taken_then_released_when_the_order_is_cancelled(): void
    {
        $this->stock(2);
        $voucher = Voucher::factory()->create(['code' => 'HEMAT5K', 'discount_type' => 'fixed', 'discount_value' => 5000, 'quota' => 1, 'applies_to_all' => true, 'requires_claim' => false]);

        $this->buy(['voucher' => 'hemat5k'])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame([5000, 'HEMAT5K', 45000], [$order->voucher_discount, $order->voucher_code, $order->total]);
        $this->assertSame(1, $voucher->fresh()->used_count);

        // Kuota habis untuk pembeli lain selama pesanan masih menahan voucher.
        $this->actingAs(User::factory()->create())->post('/produk/netflix/beli', $this->payload(['voucher' => 'HEMAT5K']))->assertSessionHas('error');

        $this->actingAs($this->user)->post("/pesanan/{$order->code}/batal")->assertRedirect(route('orders.index'));
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_an_invalid_voucher_blocks_the_order_and_a_claim_only_voucher_needs_a_claim(): void
    {
        $this->stock(2);
        $voucher = Voucher::factory()->create(['code' => 'KLAIM10', 'applies_to_all' => true, 'requires_claim' => true]);

        $this->buy(['voucher' => 'NGAWUR'])->assertSessionHas('error', 'Kode voucher tidak valid atau sudah berakhir.');
        $this->buy(['voucher' => 'KLAIM10'])->assertSessionHas('error', 'Voucher ini perlu diklaim dulu di halaman Voucher.');
        $this->assertSame(0, Order::count());

        UserVoucher::create(['user_id' => $this->user->id, 'voucher_id' => $voucher->id, 'claimed_at' => now()]);
        $this->buy(['voucher' => 'KLAIM10'])->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    public function test_per_user_limit_counts_live_orders_but_not_failed_ones(): void
    {
        $this->stock(3);
        Voucher::factory()->create(['code' => 'SEKALI', 'applies_to_all' => true, 'requires_claim' => false, 'per_user_limit' => 1]);

        $this->buy(['voucher' => 'SEKALI'])->assertRedirect();
        $this->buy(['voucher' => 'SEKALI'])->assertSessionHas('error', 'Kamu sudah mencapai batas pemakaian voucher ini.');

        $this->post('/pesanan/'.Order::firstOrFail()->code.'/batal');
        $this->buy(['voucher' => 'SEKALI'])->assertRedirect();
        $this->assertSame(2, Order::count());
    }

    // ---------- halaman bayar ----------

    public function test_only_the_owner_can_open_an_order_and_the_pay_page_shows_qris_and_total(): void
    {
        $this->stock();
        $this->buy();
        $order = Order::firstOrFail();

        $this->actingAs($this->user)->get("/pesanan/{$order->code}")->assertOk()
            ->assertSee($order->code)->assertSee('Scan QRIS')->assertSee('Rp 50.000', false)->assertSee('Saya Sudah Bayar')
            ->assertSee(asset('assets/qris.jpeg'), false);

        $this->actingAs(User::factory()->create())->get("/pesanan/{$order->code}")->assertNotFound();
        auth()->logout();
        $this->get("/pesanan/{$order->code}")->assertRedirect(route('login'));
    }

    public function test_the_uploaded_qris_replaces_the_default_image(): void
    {
        $this->stock();
        Storage::fake('uploads');
        Storage::disk('uploads')->put('qris/baru.png', 'x');
        Setting::put(Setting::QRIS_IMAGE, 'qris/baru.png');
        $this->buy();

        $this->get('/pesanan/'.Order::firstOrFail()->code)->assertSee('qris/baru.png', false)->assertDontSee('assets/qris.jpeg', false);
    }

    public function test_my_orders_lists_only_my_orders(): void
    {
        $this->stock();
        $this->buy();
        Order::factory()->create(['product_name' => 'Pesanan Orang Lain']);

        $this->actingAs($this->user)->get('/pesanan')->assertOk()->assertSee('Netflix Premium')->assertDontSee('Pesanan Orang Lain');
    }

    // ---------- bayar, batal, kedaluwarsa ----------

    public function test_claiming_payment_moves_the_order_to_verifying_and_stores_a_private_proof(): void
    {
        $this->stock();
        Storage::fake('local');
        $this->buy();
        $order = Order::firstOrFail();

        $this->post("/pesanan/{$order->code}/bayar", ['proof' => UploadedFile::fake()->image('bukti.png')])->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Verifying, $order->status);
        $this->assertNotNull($order->claimed_paid_at);
        Storage::disk('local')->assertExists($order->proof_path);

        $this->get("/pesanan/{$order->code}")->assertSee('Menunggu verifikasi');
        $this->post("/pesanan/{$order->code}/bayar")->assertSessionHas('error');
        $this->post("/pesanan/{$order->code}/batal")->assertSessionHas('error');
    }

    public function test_proof_must_be_a_small_image(): void
    {
        $this->stock();
        $this->buy();
        $order = Order::firstOrFail();

        $this->post("/pesanan/{$order->code}/bayar", ['proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')])->assertSessionHasErrors('proof');
        $this->post("/pesanan/{$order->code}/bayar", ['proof' => UploadedFile::fake()->image('besar.png')->size(5000)])->assertSessionHasErrors('proof');
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_claiming_after_the_deadline_is_refused_and_releases_everything(): void
    {
        $this->stock();
        $this->buy();
        $order = Order::firstOrFail();
        $order->update(['expires_at' => now()->subMinute()]);

        $this->post("/pesanan/{$order->code}/bayar")->assertSessionHas('error');

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(1, Account::where('status', AccountStatus::Available)->count());
    }

    public function test_expire_command_releases_the_voucher_only_for_overdue_pending_orders(): void
    {
        $this->stock(2);
        $voucher = Voucher::factory()->create(['code' => 'LEPAS', 'applies_to_all' => true, 'requires_claim' => false, 'per_user_limit' => 5]);
        $this->buy(['voucher' => 'LEPAS']);
        $overdue = Order::firstOrFail();
        $overdue->update(['expires_at' => now()->subMinute()]);
        $this->buy();
        $fresh = Order::where('id', '!=', $overdue->id)->firstOrFail();

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $fresh->fresh()->status);
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertSame(2, Account::where('status', AccountStatus::Available)->count());
        $this->assertNull($overdue->deliveries()->first()->account_id);
    }

    public function test_status_endpoint_reports_the_current_status_to_the_owner_only(): void
    {
        $this->stock();
        $this->buy();
        $order = Order::firstOrFail();

        $this->get("/pesanan/{$order->code}/status")->assertOk()->assertExactJson(['status' => 'pending']);
        $this->actingAs(User::factory()->create())->get("/pesanan/{$order->code}/status")->assertNotFound();
    }

    // ---------- service ----------

    public function test_restoring_an_expired_order_requeues_it_without_linking_an_account(): void
    {
        $this->stock(1);
        $this->buy();
        $order = Order::firstOrFail();
        $order->update(['expires_at' => now()->subMinute()]);
        $this->artisan('orders:expire');

        $restored = app(OrderService::class)->restore($order->fresh());

        $this->assertSame(OrderStatus::Verifying, $restored->status);
        $this->assertNull($restored->deliveries()->first()->account_id);
        $this->assertSame(1, Account::where('status', AccountStatus::Available)->count());
    }
}
