<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Mail\AccountDeliveredMail;
use App\Mail\PaymentClaimedMail;
use App\Mail\StockShortMail;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MonthlyDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        Mail::fake();
        $this->product = Product::factory()->create(['name' => 'Netflix Premium']);
    }

    private function notifyOn(): void
    {
        Setting::put(Setting::NOTIFY_EMAIL_ENABLED, true);
        Setting::put(Setting::NOTIFY_EMAIL_TO, 'admin@toko.test');
    }

    private function stock(int $count = 1, array $attrs = []): void
    {
        Account::factory()->count($count)->create(array_merge(['product_id' => $this->product->id, 'cogs' => 11000], $attrs));
    }

    /**
     * Pesanan 3 bulan yang sudah lunas: bulan ke-1 terkirim, bulan ke-2 jatuh tempo $dueIn1 hari dari hari ini, bulan ke-3 $dueIn2 hari.
     *
     * @return array{0: Order, 1: OrderDelivery, 2: OrderDelivery}
     */
    private function paidOrder(int $dueIn1 = 0, int $dueIn2 = 28, array $attrs = []): array
    {
        $first = Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $this->product->id]);
        $order = Order::factory()->status(OrderStatus::Paid)->create(array_merge([
            'user_id' => User::factory()->create()->id, 'product_id' => $this->product->id, 'product_name' => 'Netflix Premium', 'months' => 3,
            'customer_name' => 'Budi Santoso', 'customer_email' => 'budi@uji.test', 'warranty_name' => 'Garansi 7 Hari', 'warranty_days' => 7, 'paid_at' => now()->subDays(28),
        ], $attrs));
        $order->deliveries()->create(['sequence' => 1, 'account_id' => $first->id, 'cogs' => 9000, 'delivered_at' => now()->subDays(28), 'warranty_ends_on' => today()->subDays(21)->toDateString()]);
        $d2 = $order->deliveries()->create(['sequence' => 2, 'due_on' => today()->addDays($dueIn1)->toDateString()]);
        $d3 = $order->deliveries()->create(['sequence' => 3, 'due_on' => today()->addDays($dueIn2)->toDateString()]);

        return [$order, $d2, $d3];
    }

    // ---------- pengiriman terjadwal ----------

    public function test_a_due_delivery_gets_an_account_cogs_warranty_and_an_email(): void
    {
        $this->stock(2);
        [$order, $d2, $d3] = $this->paidOrder(0, 28);

        $this->artisan('orders:deliver-due')->assertSuccessful();

        $d2->refresh();
        $account = $d2->account;
        $this->assertTrue($d2->isDelivered());
        $this->assertSame(AccountStatus::Sold, $account->status);
        $this->assertStringContainsString($order->code, $account->sold_to);
        $this->assertSame(11000, $d2->cogs);
        $this->assertSame(today()->addDays(7)->toDateString(), $d2->warranty_ends_on->toDateString(), 'garansi dihitung per akun dari hari kirim');
        $this->assertFalse($d3->fresh()->isDelivered(), 'bulan ke-3 belum jatuh tempo');
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, Account::where('status', AccountStatus::Available)->count());

        Mail::assertQueued(AccountDeliveredMail::class, fn ($m) => $m->hasTo('budi@uji.test') && ! $m->replacement && $m->delivery->sequence === 2
            && str_contains($m->envelope()->subject, 'bulan ke-2 dari 3'));
    }

    public function test_the_order_completes_after_the_last_delivery(): void
    {
        $this->stock(3);
        [$order, $d2, $d3] = $this->paidOrder(-1, 0);

        $this->artisan('orders:deliver-due')->assertSuccessful();

        $this->assertTrue($d2->fresh()->isDelivered() && $d3->fresh()->isDelivered());
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        Mail::assertQueued(AccountDeliveredMail::class, 2);
    }

    public function test_only_running_orders_with_due_dates_are_processed_and_nothing_is_delivered_twice(): void
    {
        $this->stock(5);
        [, $notDue] = $this->paidOrder(3, 31);
        [$cancelled, $dueButCancelled] = $this->paidOrder(0, 28);
        $cancelled->forceFill(['status' => OrderStatus::Cancelled])->save();
        [, $due] = $this->paidOrder(0, 28);

        $this->artisan('orders:deliver-due');
        $this->artisan('orders:deliver-due');

        $this->assertFalse($notDue->fresh()->isDelivered());
        $this->assertFalse($dueButCancelled->fresh()->isDelivered());
        $this->assertTrue($due->fresh()->isDelivered());
        Mail::assertQueued(AccountDeliveredMail::class, 1);
        $this->assertSame(4, Account::where('status', AccountStatus::Available)->count(), 'hanya satu akun stok yang terpakai');
    }

    public function test_an_expired_stock_account_is_never_delivered(): void
    {
        $this->stock(1, ['started_at' => today()->subDays(40)->toDateString(), 'active_days' => 28]);
        [, $d2] = $this->paidOrder(0, 28);

        $this->artisan('orders:deliver-due');

        $this->assertFalse($d2->fresh()->isDelivered());
    }

    // ---------- stok kurang ----------

    public function test_without_stock_the_delivery_waits_and_the_admin_is_alerted_once_per_day(): void
    {
        $this->notifyOn();
        [, $d2] = $this->paidOrder(-2, 26);

        $this->artisan('orders:deliver-due')->assertSuccessful();

        $this->assertFalse($d2->fresh()->isDelivered());
        $this->assertNotNull($d2->fresh()->stock_alerted_at);
        Mail::assertQueued(StockShortMail::class, fn ($m) => $m->hasTo('admin@toko.test')
            && $m->lines[0]['product'] === 'Netflix Premium' && $m->lines[0]['overdue'] === 1 && $m->lines[0]['stock'] === 0);

        $this->artisan('orders:deliver-due');
        Mail::assertQueued(StockShortMail::class, 1);

        $this->travel(25)->hours();
        $this->artisan('orders:deliver-due');
        Mail::assertQueued(StockShortMail::class, 2);
    }

    public function test_the_late_delivery_is_sent_automatically_once_stock_arrives(): void
    {
        $this->notifyOn();
        [$order, $d2] = $this->paidOrder(-2, 26);
        $this->artisan('orders:deliver-due');
        $this->assertFalse($d2->fresh()->isDelivered());

        $this->stock(1);
        $this->artisan('orders:deliver-due');

        $d2->refresh();
        $this->assertTrue($d2->isDelivered());
        $this->assertNull($d2->stock_alerted_at);
        Mail::assertQueued(AccountDeliveredMail::class, 1);
    }

    public function test_no_alert_email_when_notifications_are_off(): void
    {
        [, $d2] = $this->paidOrder(-2, 26);

        $this->artisan('orders:deliver-due')->assertSuccessful();

        $this->assertFalse($d2->fresh()->isDelivered());
        Mail::assertNothingOutgoing();
    }

    public function test_an_early_warning_is_sent_once_a_day_when_next_weeks_need_exceeds_stock(): void
    {
        $this->notifyOn();
        $this->stock(1);
        $this->paidOrder(2, 30);
        $this->paidOrder(4, 32);
        $this->paidOrder(30, 58);

        $this->artisan('orders:deliver-due');
        Mail::assertQueued(StockShortMail::class, fn ($m) => $m->lines[0]['overdue'] === 0 && $m->lines[0]['upcoming'] === 2 && $m->lines[0]['stock'] === 1);

        $this->artisan('orders:deliver-due');
        Mail::assertQueued(StockShortMail::class, 1);

        Cache::flush();
        Account::query()->delete();
        $this->stock(5);
        $this->artisan('orders:deliver-due');
        Mail::assertQueued(StockShortMail::class, 1);
    }

    // ---------- kirim manual oleh admin ----------

    public function test_the_admin_can_send_the_next_month_early_when_stock_exists(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->stock(1);
        [$order, , $d3] = $this->paidOrder(10, 38);

        $this->post("/admin/pesanan/{$order->id}/kiriman/{$d3->id}/kirim")->assertSessionHas('success');

        $this->assertTrue($d3->fresh()->isDelivered());
        Mail::assertQueued(AccountDeliveredMail::class, 1);
    }

    public function test_manual_send_fails_cleanly_without_stock_or_for_the_wrong_state(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        [$order, $d2] = $this->paidOrder(0, 28);
        [$otherOrder] = $this->paidOrder(0, 28);

        $this->post("/admin/pesanan/{$order->id}/kiriman/{$d2->id}/kirim")->assertSessionHas('error');
        $this->post("/admin/pesanan/{$otherOrder->id}/kiriman/{$d2->id}/kirim")->assertNotFound();

        $this->stock(2);
        $this->post("/admin/pesanan/{$order->id}/kiriman/{$d2->id}/kirim")->assertSessionHas('success');
        $this->post("/admin/pesanan/{$order->id}/kiriman/{$d2->id}/kirim")->assertSessionHas('error');

        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
        $this->post("/admin/pesanan/{$order->id}/kiriman/".$order->deliveries()->where('sequence', 3)->value('id').'/kirim')->assertSessionHas('error');
        $this->assertSame(1, Account::where('status', AccountStatus::Available)->count());
    }

    public function test_manual_send_is_admin_only(): void
    {
        [$order, $d2] = $this->paidOrder(0, 28);
        $url = "/admin/pesanan/{$order->id}/kiriman/{$d2->id}/kirim";

        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post($url)->assertForbidden();
    }

    // ---------- tampilan admin dan pelanggan ----------

    public function test_the_order_list_counts_and_filters_overdue_deliveries(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        [$late] = $this->paidOrder(-1, 27);
        $this->paidOrder(5, 33);

        $this->get('/admin/pesanan')->assertInertia(fn ($page) => $page->where('stats.overdue', 1));
        $this->get('/admin/pesanan?status=overdue')->assertInertia(fn ($page) => $page
            ->where('filters.status', 'overdue')->has('orders.data', 1)->where('orders.data.0.code', $late->code));
    }

    public function test_the_customer_sees_preparing_for_a_due_month_and_the_date_for_a_future_one(): void
    {
        [$order] = $this->paidOrder(0, 12);

        $this->actingAs($order->user)->get("/pesanan/{$order->code}")->assertOk()
            ->assertSee('Sedang disiapkan')->assertSee('Akun bulan ini dikirim otomatis')->assertSee(today()->addDays(12)->translatedFormat('j F Y'));
    }

    // ---------- notifikasi pembayaran masuk ----------

    public function test_the_admin_is_emailed_when_a_customer_claims_payment_if_notifications_are_on(): void
    {
        $this->notifyOn();
        $order = Order::factory()->create(['product_id' => $this->product->id, 'product_name' => 'Netflix Premium', 'total' => 45000]);
        $this->actingAs($order->user);

        $this->post("/pesanan/{$order->code}/bayar")->assertSessionHas('success');

        Mail::assertQueued(PaymentClaimedMail::class, fn ($m) => $m->hasTo('admin@toko.test') && str_contains($m->render(), $order->code) && str_contains($m->render(), 'Rp 45.000'));
    }

    public function test_no_payment_email_when_off_and_a_failed_claim_sends_nothing(): void
    {
        $order = Order::factory()->create();
        $this->actingAs($order->user);
        $this->post("/pesanan/{$order->code}/bayar");
        Mail::assertNothingOutgoing();

        $this->notifyOn();
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
        $this->post("/pesanan/{$order->code}/bayar")->assertSessionHas('error');
        Mail::assertNothingOutgoing();
    }

    public function test_each_notification_type_has_its_own_switch(): void
    {
        $this->notifyOn();
        Setting::put('notify.email.type.payment', false);
        $order = Order::factory()->create(['product_id' => $this->product->id]);
        $this->actingAs($order->user)->post("/pesanan/{$order->code}/bayar")->assertSessionHas('success');
        Mail::assertNothingOutgoing();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/pengaturan')->assertInertia(fn ($page) => $page
            ->where('notifications.types.1', ['type' => 'payment', 'label' => 'Pembayaran menunggu verifikasi', 'enabled' => false])
            ->where('notifications.types.0.enabled', true));

        $this->put('/admin/pengaturan/notifikasi', ['email_enabled' => true, 'email_to' => 'admin@toko.test', 'types' => ['report' => false, 'payment' => true, 'stock' => true]])->assertSessionHas('success');
        $this->assertFalse(\App\Services\AdminNotifier::typeEnabled('report'));
        $this->assertTrue(\App\Services\AdminNotifier::typeEnabled('payment'));
    }

    public function test_the_command_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('orders:deliver-due')->assertSuccessful();
    }
}
