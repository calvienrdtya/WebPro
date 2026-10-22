<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Mail\AccountDeliveredMail;
use App\Mail\PaymentReceivedMail;
use App\Mail\StockShortMail;
use App\Models\Account;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->product = Product::factory()->create(['name' => 'Netflix Premium']);
        $this->actingAs($this->admin);
    }

    /** Pesanan menunggu verifikasi (akun belum dikaitkan) dengan satu akun tersedia di stok. */
    private function verifying(array $attrs = [], int $months = 1): Order
    {
        Account::factory()->create(['product_id' => $this->product->id, 'cogs' => 15000, 'email' => 'akun@contoh.test', 'password' => 'rahasia123']);
        $order = Order::factory()->status(OrderStatus::Verifying)->create(array_merge([
            'product_id' => $this->product->id, 'months' => $months, 'customer_name' => 'Budi Santoso', 'customer_email' => 'budi@uji.test',
            'claimed_paid_at' => now()->subMinutes(5),
        ], $attrs));
        foreach (range(1, $months) as $i) {
            $order->deliveries()->create(['sequence' => $i]);
        }

        return $order;
    }

    public function test_guests_and_regular_users_cannot_reach_order_routes(): void
    {
        $order = $this->verifying();
        $delivery = $order->deliveries()->first();
        $routes = [
            ['get', '/admin/pesanan'], ['patch', "/admin/pesanan/{$order->id}/konfirmasi"], ['patch', "/admin/pesanan/{$order->id}/tolak"],
            ['patch', "/admin/pesanan/{$order->id}/pulihkan"], ['post', "/admin/pesanan/{$order->id}/kiriman/{$delivery->id}/kirim-ulang"],
            ['get', "/admin/pesanan/{$order->id}/bukti"], ['post', '/admin/pengaturan/qris'], ['patch', "/admin/pesanan/{$order->id}/catatan"],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertSame(OrderStatus::Verifying, $order->fresh()->status);
    }

    public function test_confirming_delivers_the_account_copies_cogs_and_emails_the_credentials(): void
    {
        Mail::fake();
        $order = $this->verifying(['warranty_name' => 'Garansi Penuh', 'warranty_days' => 28]);
        $account = Account::firstWhere('email', 'akun@contoh.test');
        $this->assertNull($order->deliveries()->first()->account_id);

        $this->patch("/admin/pesanan/{$order->id}/konfirmasi")->assertSessionHas('success');

        $order->refresh();
        $delivery = $order->deliveries()->first();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame($this->admin->id, $order->verified_by);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(AccountStatus::Sold, $account->fresh()->status);
        $this->assertStringContainsString($order->code, $account->fresh()->sold_to);
        $this->assertNotNull($account->fresh()->sold_at);
        $this->assertSame(15000, $delivery->cogs);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame(today()->addDays(28)->toDateString(), $delivery->warranty_ends_on->toDateString());

        Mail::assertNotQueued(PaymentReceivedMail::class);
        Mail::assertQueued(AccountDeliveredMail::class, fn ($mail) => $mail->hasTo('budi@uji.test')
            && str_contains($mail->render(), 'akun@contoh.test') && str_contains($mail->render(), 'rahasia123'));
    }

    public function test_cogs_snapshot_stays_when_the_account_cogs_is_edited_later(): void
    {
        Mail::fake();
        $order = $this->verifying();
        $this->patch("/admin/pesanan/{$order->id}/konfirmasi");

        $order->deliveries()->first()->account->update(['cogs' => 99999]);

        $this->assertSame(15000, $order->deliveries()->first()->cogs);
    }

    public function test_no_warranty_means_no_warranty_end_date(): void
    {
        Mail::fake();
        $order = $this->verifying();

        $this->patch("/admin/pesanan/{$order->id}/konfirmasi");

        $this->assertNull($order->deliveries()->first()->warranty_ends_on);
    }

    public function test_multi_month_order_stays_paid_and_schedules_the_next_deliveries(): void
    {
        Mail::fake();
        $order = $this->verifying(months: 3);

        $this->patch("/admin/pesanan/{$order->id}/konfirmasi");

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $deliveries = $order->deliveries;
        $this->assertTrue($deliveries[0]->isDelivered());
        $this->assertSame([false, false], [$deliveries[1]->isDelivered(), $deliveries[2]->isDelivered()]);
        $this->assertSame(today()->addDays(28)->toDateString(), $deliveries[1]->due_on->toDateString());
        $this->assertSame(today()->addDays(56)->toDateString(), $deliveries[2]->due_on->toDateString());
        $this->assertSame(1, Account::where('status', AccountStatus::Sold)->count());
    }

    public function test_confirming_twice_or_a_closed_order_is_refused(): void
    {
        Mail::fake();
        $order = $this->verifying();
        $this->patch("/admin/pesanan/{$order->id}/konfirmasi")->assertSessionHas('success');
        $this->patch("/admin/pesanan/{$order->id}/konfirmasi")->assertSessionHas('error');

        Mail::assertQueued(AccountDeliveredMail::class, 1);
    }

    public function test_a_pending_order_can_be_confirmed_directly_when_the_customer_forgot_to_press_paid(): void
    {
        Mail::fake();
        $order = $this->verifying();
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        $this->patch("/admin/pesanan/{$order->id}/konfirmasi")->assertSessionHas('success');

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_rejecting_releases_the_voucher_quota_leaves_stock_untouched_and_stores_the_reason(): void
    {
        $voucher = Voucher::factory()->create(['used_count' => 1, 'quota' => 5]);
        $order = $this->verifying(['voucher_id' => $voucher->id, 'voucher_code' => $voucher->code, 'voucher_discount' => 1000]);
        $account = Account::firstWhere('email', 'akun@contoh.test');

        $this->patch("/admin/pesanan/{$order->id}/tolak", ['reason' => 'Nominal tidak cocok'])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame([OrderStatus::Rejected, 'Nominal tidak cocok'], [$order->status, $order->reject_reason]);
        $this->assertSame(AccountStatus::Available, $account->fresh()->status);
        $this->assertNull($order->deliveries()->first()->account_id);
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->patch("/admin/pesanan/{$order->id}/tolak")->assertSessionHas('error');
    }

    public function test_restoring_an_expired_order_takes_the_voucher_quota_again_without_touching_stock(): void
    {
        $voucher = Voucher::factory()->create(['used_count' => 0]);
        $order = $this->verifying(['voucher_id' => $voucher->id, 'voucher_code' => $voucher->code]);
        $order->forceFill(['status' => OrderStatus::Expired])->save();
        Account::query()->delete();

        $this->patch("/admin/pesanan/{$order->id}/pulihkan")->assertSessionHas('success');

        $this->assertSame(OrderStatus::Verifying, $order->fresh()->status);
        $this->assertNull($order->deliveries()->first()->account_id);
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->patch("/admin/pesanan/{$order->id}/pulihkan")->assertSessionHas('error');
    }

    public function test_resend_only_works_for_a_delivered_account(): void
    {
        Mail::fake();
        $order = $this->verifying();
        $delivery = $order->deliveries()->first();

        $this->post("/admin/pesanan/{$order->id}/kiriman/{$delivery->id}/kirim-ulang")->assertSessionHas('error');
        Mail::assertNothingOutgoing();

        $this->patch("/admin/pesanan/{$order->id}/konfirmasi");
        $this->post("/admin/pesanan/{$order->id}/kiriman/{$delivery->id}/kirim-ulang")->assertSessionHas('success');
        Mail::assertQueued(AccountDeliveredMail::class, 2);

        $other = $this->verifying();
        $this->post("/admin/pesanan/{$order->id}/kiriman/{$other->deliveries()->first()->id}/kirim-ulang")->assertNotFound();
    }

    public function test_internal_note_can_be_saved_and_cleared_and_is_exposed_with_account_and_service_dates(): void
    {
        Mail::fake();
        $order = $this->verifying(['warranty_name' => 'Garansi Penuh', 'warranty_days' => 28]);
        $this->patch("/admin/pesanan/{$order->id}/konfirmasi");

        $this->patch("/admin/pesanan/{$order->id}/catatan", ['note' => 'Pelanggan minta ganti profil'])->assertSessionHas('success');
        $this->assertSame('Pelanggan minta ganti profil', $order->fresh()->admin_note);
        $this->patch("/admin/pesanan/{$order->id}/catatan", ['note' => str_repeat('x', 2001)])->assertSessionHasErrors('note');

        $this->get('/admin/pesanan')->assertInertia(fn ($page) => $page
            ->where('orders.data.0.admin_note', 'Pelanggan minta ganti profil')
            ->where('orders.data.0.service_ends_on', today()->addDays(28)->toDateString())
            ->where('orders.data.0.deliveries.0.account.started_at', today()->toDateString())
            ->where('orders.data.0.deliveries.0.account.expires_at', today()->addDays(28)->toDateString())
            ->where('orders.data.0.deliveries.0.warranty_ends_on', today()->addDays(28)->toDateString()));

        $this->patch("/admin/pesanan/{$order->id}/catatan", ['note' => ''])->assertSessionHas('success');
        $this->assertNull($order->fresh()->admin_note);
    }

    public function test_proof_is_served_to_admins_from_private_storage(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('proofs/a.png', 'isi-gambar');
        $order = $this->verifying(['proof_path' => 'proofs/a.png']);

        $this->get("/admin/pesanan/{$order->id}/bukti")->assertOk();
        $this->get('/admin/pesanan/'.$this->verifying()->id.'/bukti')->assertNotFound();
    }

    public function test_index_defaults_to_all_and_the_verification_queue_tab_is_oldest_first_and_filters_by_tab_and_search(): void
    {
        $newer = $this->verifying(['code' => 'ORD-NEWER', 'claimed_paid_at' => now()->subMinute()]);
        $older = $this->verifying(['code' => 'ORD-OLDER', 'claimed_paid_at' => now()->subHour()]);
        Order::factory()->create(['code' => 'ORD-WAIT']);
        Order::factory()->status(OrderStatus::Expired)->create(['code' => 'ORD-EXP']);

        $this->get('/admin/pesanan')->assertInertia(fn ($page) => $page->where('filters.status', 'all')->has('orders.data', 4));

        $this->get('/admin/pesanan?status=queue')->assertInertia(fn ($page) => $page
            ->where('filters.status', 'queue')
            ->where('orders.data.0.code', 'ORD-OLDER')->where('orders.data.1.code', 'ORD-NEWER')->has('orders.data', 2)
            ->where('counts.queue', 2)->where('counts.unpaid', 1)->where('counts.failed', 1)->where('counts.all', 4)
            ->where('stats.to_verify', 2));

        $this->get('/admin/pesanan?status=failed')->assertInertia(fn ($page) => $page->has('orders.data', 1)->where('orders.data.0.code', 'ORD-EXP'));
        $this->get('/admin/pesanan?status=all&q=ORD-WAIT')->assertInertia(fn ($page) => $page->has('orders.data', 1)->where('orders.data.0.code', 'ORD-WAIT'));
        $this->get('/admin/pesanan?status=ngawur')->assertInertia(fn ($page) => $page->where('filters.status', 'all'));
    }

    public function test_index_exposes_credentials_for_delivered_orders_and_revenue_stats(): void
    {
        Mail::fake();
        $order = $this->verifying(['total' => 40000]);
        $this->patch("/admin/pesanan/{$order->id}/konfirmasi");

        $this->get('/admin/pesanan?status=paid')->assertInertia(fn ($page) => $page
            ->where('orders.data.0.deliveries.0.account.email', 'akun@contoh.test')
            ->where('orders.data.0.deliveries.0.account.password', 'rahasia123')
            ->where('stats.today', 1)->where('stats.month_revenue', 40000));
    }

    // ---------- pengaruh ke modul lain ----------

    public function test_users_with_orders_cannot_be_deleted_but_can_be_blocked(): void
    {
        $buyer = User::factory()->create();
        Order::factory()->create(['user_id' => $buyer->id]);
        $free = User::factory()->create();

        $this->delete("/admin/user/{$buyer->id}")->assertSessionHas('error');
        $this->assertNotNull(User::find($buyer->id));

        $this->delete('/admin/user/massal', ['ids' => [$buyer->id, $free->id]])->assertSessionHas('success');
        $this->assertNotNull(User::find($buyer->id));
        $this->assertNull(User::find($free->id));

        $this->patch("/admin/user/{$buyer->id}/blokir")->assertSessionHas('success');
        $this->assertTrue($buyer->fresh()->isBlocked());
    }

    public function test_confirming_takes_the_oldest_unexpired_available_account(): void
    {
        Mail::fake();
        $order = $this->verifying();
        Account::query()->delete();
        $expired = Account::factory()->create(['product_id' => $this->product->id, 'started_at' => today()->subDays(40), 'active_days' => 28]);
        $sold = Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $this->product->id]);
        $oldest = Account::factory()->create(['product_id' => $this->product->id]);
        $newer = Account::factory()->create(['product_id' => $this->product->id]);

        $this->patch("/admin/pesanan/{$order->id}/konfirmasi")->assertSessionHas('success');

        $this->assertSame($oldest->id, $order->deliveries()->first()->account_id);
        $this->assertSame(AccountStatus::Sold, $oldest->fresh()->status);
        $this->assertSame(AccountStatus::Available, $newer->fresh()->status);
        $this->assertSame(AccountStatus::Available, $expired->fresh()->status);
    }

    public function test_confirming_without_stock_records_payment_waits_for_stock_alerts_admin_and_delivers_later(): void
    {
        Mail::fake();
        Setting::put(Setting::NOTIFY_EMAIL_ENABLED, true);
        Setting::put(Setting::NOTIFY_EMAIL_TO, 'admin@toko.test');
        $order = $this->verifying(['warranty_name' => 'Garansi Penuh', 'warranty_days' => 7], months: 2);
        Account::query()->delete();

        $this->patch("/admin/pesanan/{$order->id}/konfirmasi")->assertSessionHas('warning')->assertSessionMissing('success');

        $order->refresh();
        $first = $order->deliveries()->where('sequence', 1)->first();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNull($first->account_id);
        $this->assertSame(today()->toDateString(), $first->due_on->toDateString());
        Mail::assertNotQueued(AccountDeliveredMail::class);
        Mail::assertQueued(StockShortMail::class, fn ($m) => $m->hasTo('admin@toko.test'));
        Mail::assertQueued(PaymentReceivedMail::class, fn ($m) => $m->hasTo('budi@uji.test') && str_contains($m->render(), $order->code));

        // Stok ditambah dua hari kemudian: pengiriman terjadwal mengirim bulan ke-1 dan jadwal bulan ke-2 mengikuti hari kirim.
        $this->travel(2)->days();
        $account = Account::factory()->create(['product_id' => $this->product->id]);
        $this->artisan('orders:deliver-due')->assertSuccessful();

        $first->refresh();
        $this->assertSame($account->id, $first->account_id);
        $this->assertNotNull($first->delivered_at);
        $this->assertSame(today()->addDays(7)->toDateString(), $first->warranty_ends_on->toDateString());
        $this->assertSame(today()->addDays(28)->toDateString(), $order->deliveries()->where('sequence', 2)->first()->due_on->toDateString());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Mail::assertQueued(AccountDeliveredMail::class, 1);
    }

    public function test_a_single_month_order_waiting_for_stock_completes_when_sent_manually(): void
    {
        Mail::fake();
        $order = $this->verifying();
        Account::query()->delete();
        $this->patch("/admin/pesanan/{$order->id}/konfirmasi")->assertSessionHas('warning');
        $delivery = $order->deliveries()->first();

        $this->post("/admin/pesanan/{$order->id}/kiriman/{$delivery->id}/kirim")->assertSessionHas('error');
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        Account::factory()->create(['product_id' => $this->product->id]);
        $this->post("/admin/pesanan/{$order->id}/kiriman/{$delivery->id}/kirim")->assertSessionHas('success');

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertNotNull($delivery->fresh()->delivered_at);
    }

    public function test_qris_image_can_be_uploaded_replaced_and_reset(): void
    {
        Storage::fake('uploads');

        $this->post('/admin/pengaturan/qris', [])->assertSessionHasErrors('qris');
        $this->post('/admin/pengaturan/qris', ['qris' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('qris');

        $this->post('/admin/pengaturan/qris', ['qris' => UploadedFile::fake()->image('qris.png')])->assertSessionHas('success');
        $first = Setting::get(Setting::QRIS_IMAGE);
        Storage::disk('uploads')->assertExists($first);
        $this->assertStringContainsString($first, Setting::qrisUrl());

        $this->post('/admin/pengaturan/qris', ['qris' => UploadedFile::fake()->image('baru.png')]);
        Storage::disk('uploads')->assertMissing($first);

        $this->delete('/admin/pengaturan/qris')->assertSessionHas('success');
        $this->assertStringContainsString('assets/qris.jpeg', Setting::qrisUrl());
        $this->assertSame([], Storage::disk('uploads')->allFiles('qris'));
    }
}
