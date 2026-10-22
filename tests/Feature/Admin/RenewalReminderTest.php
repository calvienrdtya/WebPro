<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Mail\RenewalReminderMail;
use App\Models\Account;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RenewalReminderTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->product = Product::factory()->create(['name' => 'Netflix Premium', 'slug' => 'netflix']);
    }

    /** Pesanan selesai yang akun terakhirnya habis $inDays hari lagi. */
    private function completed(int $inDays, array $attrs = [], int $months = 1): Order
    {
        $order = Order::factory()->status(OrderStatus::Completed)->create(array_merge([
            'product_id' => $this->product->id, 'months' => $months, 'customer_email' => 'budi@uji.test', 'paid_at' => now()->subDays(25),
        ], $attrs));
        foreach (range(1, $months) as $i) {
            $account = Account::factory()->status(AccountStatus::Sold)->create([
                'product_id' => $this->product->id, 'started_at' => today()->subDays(28 - $inDays - ($months - $i) * 28)->toDateString(), 'active_days' => 28,
            ]);
            $order->deliveries()->create(['sequence' => $i, 'account_id' => $account->id, 'delivered_at' => now()->subDays(25)]);
        }

        return $order;
    }

    public function test_sends_once_for_orders_whose_last_account_ends_within_the_window(): void
    {
        $soon = $this->completed(3);
        $today = $this->completed(0);
        $later = $this->completed(4);
        $past = $this->completed(-1);

        $this->artisan('orders:remind-renewal')->assertSuccessful();

        Mail::assertQueued(RenewalReminderMail::class, 2);
        Mail::assertQueued(RenewalReminderMail::class, fn ($m) => $m->order->is($soon) && $m->hasTo('budi@uji.test')
            && str_contains($m->render(), $soon->code) && str_contains($m->render(), '/produk/netflix'));
        $this->assertNotNull($soon->fresh()->renewal_reminded_at);
        $this->assertNotNull($today->fresh()->renewal_reminded_at);
        $this->assertNull($later->fresh()->renewal_reminded_at);
        $this->assertNull($past->fresh()->renewal_reminded_at);

        $this->artisan('orders:remind-renewal');
        Mail::assertQueued(RenewalReminderMail::class, 2);
    }

    public function test_skips_customers_who_already_ordered_the_same_product_again(): void
    {
        $order = $this->completed(2);
        Order::factory()->status(OrderStatus::Verifying)->create(['user_id' => $order->user_id, 'product_id' => $this->product->id]);

        $this->artisan('orders:remind-renewal');

        Mail::assertNothingOutgoing();
        $this->assertNotNull($order->fresh()->renewal_reminded_at);
    }

    public function test_respects_the_switch_the_day_count_and_running_multi_month_orders(): void
    {
        $order = $this->completed(5);
        $running = $this->completed(1, months: 2);
        $running->forceFill(['status' => OrderStatus::Paid])->save();

        Setting::put(Setting::RENEWAL_ENABLED, false);
        $this->artisan('orders:remind-renewal');
        Mail::assertNothingOutgoing();

        Setting::put(Setting::RENEWAL_ENABLED, true);
        Setting::put(Setting::RENEWAL_DAYS, '7');
        $this->artisan('orders:remind-renewal');
        Mail::assertQueued(RenewalReminderMail::class, fn ($m) => $m->order->is($order));
        Mail::assertQueued(RenewalReminderMail::class, 1);
    }

    public function test_admin_can_save_the_reminder_settings_within_range(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/pengaturan/pengingat', ['enabled' => false, 'days' => 5])->assertSessionHas('success');
        $this->assertFalse(Setting::renewalEnabled());
        $this->assertSame(5, Setting::renewalDays());

        $this->put('/admin/pengaturan/pengingat', ['enabled' => true, 'days' => 99])->assertSessionHasErrors('days');
        $this->actingAs(User::factory()->create())->put('/admin/pengaturan/pengingat', ['enabled' => true, 'days' => 3])->assertForbidden();
    }
}
