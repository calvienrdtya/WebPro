<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFinanceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::factory()->create(['name' => 'Netflix Premium']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    /** Pesanan lunas 1 bulan dengan satu akun terkirim ber-HPP $cogs. */
    private function sale(array $attrs = [], int $cogs = 12000, ?OrderStatus $status = null): Order
    {
        $order = Order::factory()->status($status ?? OrderStatus::Completed)->create(array_merge([
            'product_id' => $this->product->id, 'product_name' => 'Netflix Premium',
            'list_price' => 50000, 'product_price' => 40000, 'warranty_price' => 10000, 'service_fee' => 1000, 'voucher_discount' => 2000, 'total' => 49000,
            'paid_at' => now(),
        ], $attrs));
        $account = Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $this->product->id, 'cogs' => $cogs]);
        $order->deliveries()->create(['sequence' => 1, 'account_id' => $account->id, 'cogs' => $cogs, 'delivered_at' => $order->paid_at]);

        return $order;
    }

    public function test_the_money_cards_follow_the_agreed_formulas(): void
    {
        $this->sale();

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('period', 'bulan-ini')
            ->where('finance.orders', 1)->where('finance.revenue', 49000)->where('finance.hpp', 12000)
            // Profit = harga produk setelah voucher - HPP = 40.000 - 2.000 - 12.000
            ->where('finance.profit', 26000)->where('finance.fee', 1000)->where('finance.warranty', 10000)
            // Total laba = Profit + Biaya admin + Garansi = Revenue - HPP
            ->where('finance.total_profit', 37000)->where('finance.hpp_estimate', 0));
    }

    public function test_only_paid_orders_count_and_they_count_on_the_payment_date(): void
    {
        $this->sale(['total' => 100000]);
        $this->sale(['total' => 200000, 'paid_at' => now()->subMonth()->startOfMonth()->addDays(3)]);
        foreach ([OrderStatus::Pending, OrderStatus::Verifying, OrderStatus::Expired, OrderStatus::Rejected, OrderStatus::Cancelled] as $status) {
            Order::factory()->status($status)->create(['total' => 999999, 'paid_at' => now()]);
        }

        $revenue = fn (string $q) => $this->get('/dashboard'.$q)->viewData('page')['props']['finance']['revenue'];

        $this->assertSame(100000, $revenue(''));
        $this->assertSame(200000, $revenue('?periode=bulan-lalu'));
        $this->assertSame(300000, $revenue('?periode=semua'));
        $this->assertSame(100000, $revenue('?periode=ngawur'));
    }

    public function test_the_previous_period_is_provided_for_trends_except_for_all_time(): void
    {
        $this->sale(['total' => 100000]);
        $this->sale(['total' => 40000, 'paid_at' => now()->subMonth()->startOfMonth()->addDay()]);

        $this->get('/dashboard?periode=30-hari')->assertInertia(fn ($page) => $page->has('previous.revenue'));
        $this->get('/dashboard?periode=bulan-lalu')->assertInertia(fn ($page) => $page->where('finance.revenue', 40000)->where('previous.revenue', 0));
        $this->get('/dashboard?periode=semua')->assertInertia(fn ($page) => $page->where('previous', null));
    }

    public function test_unsent_months_use_an_estimated_cogs_from_stock_then_from_history(): void
    {
        $order = $this->sale(['months' => 3, 'status' => 'paid'], 9000);
        $order->deliveries()->create(['sequence' => 2, 'due_on' => today()->addDays(28)->toDateString()]);
        $order->deliveries()->create(['sequence' => 3, 'due_on' => today()->addDays(56)->toDateString()]);

        // Tanpa stok: memakai rata-rata HPP kiriman produk yang sudah terkirim (9.000).
        $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('finance.hpp_estimate', 18000)->where('finance.hpp', 27000));

        // Dengan stok tersedia: memakai rata-rata HPP stok (10.000 dan 14.000 = 12.000).
        Account::factory()->create(['product_id' => $this->product->id, 'cogs' => 10000]);
        Account::factory()->create(['product_id' => $this->product->id, 'cogs' => 14000]);
        $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('finance.hpp_estimate', 24000)->where('finance.hpp', 33000)->where('finance.total_profit', 49000 - 33000));
    }

    public function test_claim_costs_are_not_included_yet(): void
    {
        $order = $this->sale();
        WarrantyClaim::create(['order_id' => $order->id, 'source' => 'order', 'status' => 'done', 'claim_cost' => 50000]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('finance.total_profit', 37000));
    }

    public function test_queue_cards_count_verification_unpaid_and_overdue_deliveries(): void
    {
        Order::factory()->status(OrderStatus::Verifying)->count(2)->create();
        Order::factory()->status(OrderStatus::Pending)->create();
        $late = $this->sale(['months' => 3], 9000);
        $late->forceFill(['status' => OrderStatus::Paid])->save();
        $late->deliveries()->create(['sequence' => 2, 'due_on' => today()->subDay()->toDateString()]);
        $late->deliveries()->create(['sequence' => 3, 'due_on' => today()->subDay()->toDateString()]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('queue', ['verifying' => 2, 'unpaid' => 1, 'overdue' => 1]));
    }

    public function test_charts_have_twelve_months_and_thirty_days_and_top_products_rank_by_orders(): void
    {
        $this->sale(['total' => 70000]);
        $this->sale(['total' => 30000]);
        $other = Product::factory()->create(['name' => 'Spotify Premium']);
        $this->sale(['product_id' => $other->id, 'product_name' => 'Spotify Premium', 'total' => 90000]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->has('monthly', 12)->where('monthly.11.revenue', 190000)->where('monthly.10.revenue', 0)
            ->has('daily', 30)->where('daily.29.paid', 3)->where('daily.29.created', 3)
            ->where('top_products.0', ['name' => 'Netflix Premium', 'orders' => 2, 'revenue' => 100000])
            ->where('top_products.1.name', 'Spotify Premium')
            ->has('by_status', 7));
    }

    public function test_an_empty_store_shows_zeroes_without_errors(): void
    {
        $this->get('/dashboard?periode=semua')->assertOk()->assertInertia(fn ($page) => $page
            ->where('finance.revenue', 0)->where('finance.total_profit', 0)->where('top_products', [])->has('monthly', 12));
    }
}
