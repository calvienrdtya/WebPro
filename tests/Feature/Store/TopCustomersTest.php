<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TopCustomersTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->product = Product::factory()->create();
    }

    private function buyer(string $name, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['name' => $name, 'email' => str($name)->slug().'@uji.test'], $attrs));
    }

    private function paid(User $user, int $count, int $total = 50000, $paidAt = null, OrderStatus $status = OrderStatus::Completed): void
    {
        Order::factory()->status($status)->count($count)->create([
            'user_id' => $user->id, 'product_id' => $this->product->id, 'total' => $total, 'paid_at' => $paidAt ?? now(),
        ]);
    }

    private function ranking(string $query = ''): array
    {
        return $this->get('/top-pelanggan'.$query)->assertOk()->viewData('ranking');
    }

    public function test_the_page_is_public_and_shows_an_empty_state_with_no_orders(): void
    {
        $this->get('/top-pelanggan')->assertOk()->assertSee('Top Pelanggan')->assertSee('Belum ada peringkat bulan ini')
            ->assertSee('Podium 3 pelanggan teratas')->assertDontSee('noindex', false);
    }

    public function test_customers_are_ranked_by_paid_orders_then_by_spend_with_masked_names(): void
    {
        $this->paid($this->buyer('Jenny Suryani'), 3, 40000);   // 3 order, 120.000
        $this->paid($this->buyer('Rizky Anwar Pratama'), 3, 60000); // 3 order, 180.000 (menang seri)
        $this->paid($this->buyer('Sinta'), 5, 10000);

        $ranking = $this->ranking();

        $this->assertSame(['Sinta', 'Rizky P.', 'Jenny S.'], array_column($ranking, 'name'));
        $this->assertSame([5, 3, 3], array_column($ranking, 'orders'));
        $this->assertSame([50000, 180000, 120000], array_column($ranking, 'spend'));
        $this->get('/top-pelanggan')->assertSee('JUARA 1')->assertSee('Rizky P.')->assertDontSee('Anwar')->assertDontSee('Suryani')->assertDontSee('@uji.test');
    }

    public function test_only_paid_orders_of_active_non_admin_customers_count(): void
    {
        $this->paid($this->buyer('Budi Aktif'), 1);
        $this->paid($this->buyer('Dewi Admin', ['is_admin' => true]), 9);
        $this->paid($this->buyer('Andi Blokir', ['blocked_at' => now()]), 9);
        $unpaid = $this->buyer('Maya Belum');
        foreach ([OrderStatus::Pending, OrderStatus::Verifying, OrderStatus::Expired, OrderStatus::Rejected, OrderStatus::Cancelled] as $status) {
            $this->paid($unpaid, 1, 50000, null, $status);
        }

        $this->assertSame(['Budi A.'], array_column($this->ranking(), 'name'));
    }

    public function test_the_period_tabs_filter_by_payment_date(): void
    {
        $this->paid($this->buyer('Lama Sekali'), 4, 50000, now()->subMonths(2));
        $this->paid($this->buyer('Bulan Ini'), 1, 50000, now()->startOfMonth()->addHour());
        $this->paid($this->buyer('Minggu Ini'), 2, 50000, now()->startOfWeek()->addHour());
        $this->paid($this->buyer('Bulan Lalu'), 1, 50000, now()->subMonth()->startOfMonth()->addDay());

        $names = fn (string $q) => array_column($this->ranking($q), 'name');

        $all = $names('?periode=semua');
        $this->assertSame('Lama S.', $all[0], 'paling banyak pesanan sepanjang waktu');
        $this->assertEqualsCanonicalizing(['Lama S.', 'Minggu I.', 'Bulan L.', 'Bulan I.'], $all);

        $month = $names('');
        $this->assertContains('Bulan I.', $month);
        $this->assertNotContains('Lama S.', $month);
        $this->assertNotContains('Bulan L.', $month);

        $week = $names('?periode=minggu');
        $this->assertSame('Minggu I.', $week[0]);
        $this->assertNotContains('Lama S.', $week);
        $this->assertNotContains('Bulan L.', $week);
        $this->assertSame($names(''), $names('?periode=ngawur'));
    }

    public function test_levels_follow_the_lifetime_order_count_not_the_period(): void
    {
        $legend = $this->buyer('Sang Legenda');
        $this->paid($legend, 100, 1000, now()->subYear());
        $this->paid($legend, 1);
        $elite = $this->buyer('Orang Elite');
        $this->paid($elite, 60, 1000, now()->subYear());
        $this->paid($elite, 1);
        $loyal = $this->buyer('Pelanggan Setia');
        $this->paid($loyal, 30, 1000, now()->subYear());
        $this->paid($loyal, 1);
        $this->paid($this->buyer('Anggota Baru'), 1);

        $levels = collect($this->ranking())->pluck('level', 'name');

        $this->assertSame(['Sang L.' => 'legend', 'Orang E.' => 'elite', 'Pelanggan S.' => 'loyal', 'Anggota B.' => 'member'], $levels->all());
    }

    public function test_hero_stats_count_customers_and_paid_orders_of_all_time(): void
    {
        $this->paid($this->buyer('Satu Dua'), 3, 50000, now()->subYear());
        $this->paid($this->buyer('Tiga Empat'), 2);

        $this->get('/top-pelanggan')->assertViewHas('stats', ['customers' => 2, 'orders' => 5]);
    }

    public function test_the_podium_and_the_board_split_at_rank_three_and_the_result_is_cached(): void
    {
        foreach (range(1, 5) as $i) {
            $this->paid($this->buyer("Nama{$i} Belakang"), 6 - $i);
        }

        $html = $this->get('/top-pelanggan')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'class="pod pod-'));
        $this->assertSame(2, substr_count($html, 'class="lb-row'));
        $this->assertStringContainsString('Peringkat 4: Nama4 B.', $html);

        $this->paid($this->buyer('Pendatang Baru'), 50);
        $this->assertCount(5, $this->ranking(), 'hasil dicache beberapa menit');
        Cache::flush();
        $this->assertCount(6, $this->ranking());
    }

    public function test_non_default_periods_are_not_indexed_and_the_page_is_in_the_sitemap_and_navbar(): void
    {
        $this->get('/top-pelanggan?periode=semua')->assertOk()->assertSee('noindex, follow', false);
        $this->get('/sitemap.xml')->assertSee(route('top-customers'), false);
        $this->get('/')->assertSee(route('top-customers'), false);
        $this->get('/top-pelanggan')->assertSee('aria-current="page"', false);
    }
}
