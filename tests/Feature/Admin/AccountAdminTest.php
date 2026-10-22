<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'email' => 'nf@contoh.test',
            'password' => '123',
            'profile' => 'Profil 1',
            'pin' => '1234',
            'status' => 'available',
            'started_at' => '2026-10-01',
            'active_days' => 28,
            'note' => '',
        ], $overrides);
    }

    public function test_guests_and_regular_users_cannot_reach_account_routes(): void
    {
        $account = Account::factory()->create();
        $routes = [
            ['get', '/admin/akun'], ['post', '/admin/akun'], ['put', "/admin/akun/{$account->id}"],
            ['delete', "/admin/akun/{$account->id}"],
            ['patch', '/admin/akun/massal/status'], ['delete', '/admin/akun/massal'],
            ['get', "/admin/akun/{$account->product->slug}"],
        ];

        foreach ([null, User::factory()->create()] as $user) {
            auth()->logout();
            $user && $this->actingAs($user);

            foreach ($routes as [$method, $url]) {
                $response = $this->{$method}($url);
                $user ? $response->assertForbidden() : $response->assertRedirect(route('login'));
            }
        }

        $this->assertNotNull($account->fresh());
    }

    public function test_index_lists_all_accounts_and_per_product_pages_filter_by_product(): void
    {
        $netflix = Product::factory()->create(['name' => 'Netflix', 'slug' => 'netflix']);
        $disney = Product::factory()->create(['name' => 'Disney', 'slug' => 'disney']);
        Account::factory()->count(2)->create(['product_id' => $netflix->id]);
        Account::factory()->create(['product_id' => $disney->id]);

        $this->get('/admin/akun')->assertInertia(fn ($page) => $page
            ->component('Accounts/Index')->where('product', null)->has('accounts.data', 3));

        $this->get('/admin/akun/netflix')->assertInertia(fn ($page) => $page
            ->where('product.slug', 'netflix')->has('accounts.data', 2)->where('counts.available', 2));

        $this->get('/admin/akun/tidak-ada')->assertNotFound();
    }

    public function test_index_filters_by_status_and_search_and_shows_credentials_in_the_table(): void
    {
        $product = Product::factory()->create();
        Account::factory()->create(['product_id' => $product->id, 'email' => 'cari@contoh.test', 'password' => 'rahasia', 'profile' => 'Profil 9', 'pin' => '9876']);
        Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $product->id]);

        $this->get('/admin/akun?status=sold')
            ->assertInertia(fn ($page) => $page->has('accounts.data', 1)->where('accounts.data.0.status', 'sold'));
        $this->get('/admin/akun?q=cari@contoh')->assertInertia(fn ($page) => $page
            ->has('accounts.data', 1)
            ->where('accounts.data.0.email', 'cari@contoh.test')
            ->where('accounts.data.0.password', 'rahasia')
            ->where('accounts.data.0.profile', 'Profil 9')
            ->where('accounts.data.0.pin', '9876'));
    }

    public function test_product_page_opens_on_the_available_tab_and_other_tabs_filter_by_status(): void
    {
        $product = Product::factory()->create(['slug' => 'netflix']);
        Account::factory()->count(2)->create(['product_id' => $product->id]);
        Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $product->id, 'sold_to' => 'Budi']);
        Account::factory()->status(AccountStatus::Problem)->create(['product_id' => $product->id]);

        $this->get('/admin/akun/netflix')->assertInertia(fn ($page) => $page
            ->where('filters.status', 'available')->has('accounts.data', 2)
            ->where('counts', ['available' => 2, 'sold' => 1, 'problem' => 1, 'expired' => 0]));

        $this->get('/admin/akun/netflix?status=sold')->assertInertia(fn ($page) => $page
            ->has('accounts.data', 1)->where('accounts.data.0.sold_to', 'Budi')->where('accounts.data.0.sold_at', now()->toDateString()));

        $this->get('/admin/akun/netflix?status=all')->assertInertia(fn ($page) => $page
            ->where('filters.status', 'all')->has('accounts.data', 4));

        // Halaman semua akun tetap membuka tab "Semua".
        $this->get('/admin/akun')->assertInertia(fn ($page) => $page->where('filters.status', 'all')->has('accounts.data', 4));
    }

    public function test_marking_sold_records_buyer_and_time_and_returning_to_stock_clears_them(): void
    {
        $account = Account::factory()->create();
        $update = fn (array $changes) => $this->put("/admin/akun/{$account->id}", $this->payload($account->product, $changes))->assertSessionHasNoErrors();

        $update(['status' => 'sold', 'sold_to' => 'Siti 0812']);
        $account->refresh();
        $this->assertSame('Siti 0812', $account->sold_to);
        $this->assertNotNull($account->sold_at);

        // Pindah ke bermasalah: jejak pembeli dipertahankan.
        $update(['status' => 'problem', 'sold_to' => 'Siti 0812']);
        $this->assertSame('Siti 0812', $account->fresh()->sold_to);

        // Kembali ke stok: jejak pembeli dihapus meski field masih terkirim.
        $update(['status' => 'available', 'sold_to' => 'Siti 0812']);
        $account->refresh();
        $this->assertNull($account->sold_to);
        $this->assertNull($account->sold_at);
    }

    public function test_status_can_only_be_a_known_value_when_editing(): void
    {
        $account = Account::factory()->create();

        $this->put("/admin/akun/{$account->id}", $this->payload($account->product, ['status' => 'ngawur']))->assertSessionHasErrors('status');
        $this->assertSame(AccountStatus::Available, $account->fresh()->status);
    }

    public function test_quick_status_route_no_longer_exists(): void
    {
        $account = Account::factory()->create();

        $this->patch("/admin/akun/{$account->id}/status", ['status' => 'sold'])->assertNotFound();
    }

    public function test_sold_to_can_be_set_from_the_edit_form(): void
    {
        $account = Account::factory()->create();

        $this->put("/admin/akun/{$account->id}", $this->payload($account->product, ['status' => 'sold', 'sold_to' => 'Andi']));

        $this->assertSame('Andi', $account->fresh()->sold_to);
        $this->get('/admin/akun')->assertInertia(fn ($page) => $page->where('accounts.data.0.sold_to', 'Andi'));
    }

    public function test_can_create_an_account_with_all_fields_and_password_is_stored_encrypted(): void
    {
        $product = Product::factory()->create();

        $this->from('/admin/akun/netflix')->post('/admin/akun', $this->payload($product))
            ->assertRedirect('/admin/akun/netflix')
            ->assertSessionHas('success');

        $account = Account::firstOrFail();
        $this->assertSame('123', $account->password);
        $this->assertSame('Profil 1', $account->profile);
        $this->assertNotSame('123', DB::table('accounts')->value('password'));
    }

    public function test_credentials_are_optional_and_have_no_format_rules(): void
    {
        $product = Product::factory()->create();

        // Semua kosong (akun tools tanpa profil/PIN) diterima.
        $this->post('/admin/akun', $this->payload($product, ['email' => null, 'password' => null, 'profile' => null, 'pin' => null, 'started_at' => null, 'active_days' => null]))
            ->assertSessionHasNoErrors();

        // Email berisi link/teks bebas dan password sangat sederhana diterima.
        $this->post('/admin/akun', $this->payload($product, ['email' => 'https://tools.example/join?x=1 bukan email', 'password' => '1', 'pin' => 'abcd']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('accounts', 2);
        $this->assertDatabaseHas('accounts', ['email' => 'https://tools.example/join?x=1 bukan email']);
    }

    public function test_product_and_status_are_required_and_valid(): void
    {
        $product = Product::factory()->create();
        $trashed = Product::factory()->create();
        $trashed->delete();

        $this->post('/admin/akun', $this->payload($product, ['product_id' => null]))->assertSessionHasErrors('product_id');
        $this->post('/admin/akun', $this->payload($product, ['product_id' => 9999]))->assertSessionHasErrors('product_id');
        $this->post('/admin/akun', $this->payload($product, ['product_id' => $trashed->id]))->assertSessionHasErrors('product_id');
        $this->post('/admin/akun', $this->payload($product, ['status' => 'ngawur']))->assertSessionHasErrors('status');
        $this->post('/admin/akun', $this->payload($product, ['started_at' => 'bukan-tanggal']))->assertSessionHasErrors('started_at');
        $this->post('/admin/akun', $this->payload($product, ['email' => str_repeat('a', 1001)]))->assertSessionHasErrors('email');

        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_hpp_is_optional_whole_rupiah_and_never_negative(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/akun', $this->payload($product, ['cogs' => 20000]))->assertSessionHasNoErrors();
        $this->post('/admin/akun', $this->payload($product, ['cogs' => null]))->assertSessionHasNoErrors();
        $this->post('/admin/akun', $this->payload($product, ['cogs' => 0]))->assertSessionHasNoErrors();
        $this->assertSame([20000, null, 0], Account::orderBy('id')->pluck('cogs')->all());

        foreach ([-1, 'gratis', 12.5] as $bad) {
            $this->post('/admin/akun', $this->payload($product, ['cogs' => $bad]))->assertSessionHasErrors('cogs');
        }
        $this->assertDatabaseCount('accounts', 3);
    }

    public function test_numbers_typed_with_leading_zeros_are_accepted(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/akun', $this->payload($product, ['cogs' => '020000', 'active_days' => '028', 'profile_count' => '02']))->assertSessionHasNoErrors();

        $this->assertSame([20000, 28], [Account::first()->cogs, Account::first()->active_days]);
        $this->assertDatabaseCount('accounts', 2);
    }

    public function test_profile_count_creates_several_rows_with_same_credentials_and_hpp(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/akun', $this->payload($product, ['email' => 'nf@contoh.test', 'cogs' => 20000, 'profile' => 'Profil', 'profile_count' => 5]))
            ->assertSessionHas('success', '5 akun ditambahkan.');

        $rows = Account::orderBy('id')->get();
        $this->assertCount(5, $rows);
        $this->assertSame(['Profil 1', 'Profil 2', 'Profil 3', 'Profil 4', 'Profil 5'], $rows->pluck('profile')->all());
        $this->assertSame(['nf@contoh.test'], $rows->pluck('email')->unique()->values()->all());
        $this->assertSame([20000], $rows->pluck('cogs')->unique()->values()->all());
        $this->assertSame(['123'], $rows->pluck('password')->unique()->values()->all());
    }

    public function test_profile_count_uses_the_profile_field_as_prefix_and_defaults_to_profil(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/akun', $this->payload($product, ['profile' => 'Anak', 'profile_count' => 2]));
        $this->post('/admin/akun', $this->payload($product, ['profile' => null, 'profile_count' => 2]));
        $this->post('/admin/akun', $this->payload($product, ['profile' => 'Utama', 'profile_count' => 1]));

        $this->assertSame(['Anak 1', 'Anak 2', 'Profil 1', 'Profil 2', 'Utama'], Account::orderBy('id')->pluck('profile')->all());
    }

    public function test_profile_count_is_limited_and_ignored_on_update(): void
    {
        $product = Product::factory()->create();

        foreach ([0, 21, 'banyak'] as $bad) {
            $this->post('/admin/akun', $this->payload($product, ['profile_count' => $bad]))->assertSessionHasErrors('profile_count');
        }
        $this->assertDatabaseCount('accounts', 0);

        $account = Account::factory()->create();
        $this->put("/admin/akun/{$account->id}", $this->payload($account->product, ['profile_count' => 5]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_index_exposes_hpp_per_row_and_summary_stats(): void
    {
        $product = Product::factory()->create(['slug' => 'netflix']);
        Account::factory()->count(2)->create(['product_id' => $product->id, 'cogs' => 20000]);
        Account::factory()->create(['product_id' => $product->id, 'cogs' => null]);
        Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $product->id, 'cogs' => 30000]);
        Account::factory()->create(['cogs' => 5000]); // produk lain

        $this->get('/admin/akun/netflix?status=all')->assertInertia(fn ($page) => $page
            ->where('accounts.data.0.cogs', 30000)
            ->where('stats', [
                'stock_count' => 3,      // 2 dengan HPP + 1 tanpa HPP, semuanya tersedia
                'total_count' => 4,      // plus 1 terjual; akun produk lain tidak ikut
                'stock_cost' => 40000,   // 2 akun tersedia x 20.000
                'average_cost' => 23333, // (20.000 + 20.000 + 30.000) / 3, akun tanpa HPP tidak dihitung
                'sold_cost' => 30000,
                'missing_cost' => 1,
            ]));

        // Ringkasan tidak ikut berubah oleh tab/pencarian dan mencakup semua produk di halaman semua akun.
        $this->get('/admin/akun?status=sold')->assertInertia(fn ($page) => $page->where('stats.stock_cost', 45000));
    }

    public function test_expiry_is_start_date_plus_the_accounts_own_active_days(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/akun', $this->payload($product, ['started_at' => '2026-10-01', 'active_days' => 28]));
        $this->post('/admin/akun', $this->payload($product, ['started_at' => '2026-10-01', 'active_days' => 30]));
        $this->post('/admin/akun', $this->payload($product, ['started_at' => '2026-09-25', 'active_days' => 365]));

        $this->assertSame(['2026-10-29', '2026-10-31', '2027-09-25'], Account::orderBy('id')->get()->map(fn ($a) => $a->expires_at->toDateString())->all());
    }

    public function test_started_at_defaults_to_today_and_active_days_to_the_configured_default(): void
    {
        $product = Product::factory()->create();
        config(['proakses.account_days' => 28]);

        $this->post('/admin/akun', $this->payload($product, ['started_at' => null, 'active_days' => null]))->assertSessionHasNoErrors();

        $account = Account::firstOrFail();
        $this->assertSame(today()->toDateString(), $account->started_at->toDateString());
        $this->assertSame(28, $account->active_days);
        $this->assertSame(today()->addDays(28)->toDateString(), $account->expires_at->toDateString());
    }

    public function test_started_at_can_be_in_the_past_for_stock_bought_earlier(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/akun', $this->payload($product, ['started_at' => today()->subDays(3)->toDateString(), 'active_days' => 28]));

        $this->assertSame(today()->addDays(25)->toDateString(), Account::firstOrFail()->expires_at->toDateString());
    }

    public function test_active_days_must_be_a_sane_whole_number(): void
    {
        $product = Product::factory()->create();

        foreach ([0, -3, 3651, 'lama', 12.5] as $bad) {
            $this->post('/admin/akun', $this->payload($product, ['active_days' => $bad]))->assertSessionHasErrors('active_days');
        }
        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_expiry_cannot_be_set_directly_and_is_recomputed_on_update(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/akun', $this->payload($product, ['expires_at' => '2099-01-01']));
        $account = Account::firstOrFail();
        $this->assertSame('2026-10-29', $account->expires_at->toDateString());

        $this->put("/admin/akun/{$account->id}", $this->payload($product, ['started_at' => '2026-11-01', 'active_days' => 10, 'expires_at' => '2099-01-01']));
        $this->assertSame('2026-11-11', $account->fresh()->expires_at->toDateString());
    }

    public function test_profile_count_rows_share_start_date_and_active_days(): void
    {
        $product = Product::factory()->create();

        $this->post('/admin/akun', $this->payload($product, ['profile_count' => 3, 'started_at' => '2026-10-01', 'active_days' => 14]));

        $this->assertSame(['2026-10-15'], Account::get()->map(fn ($a) => $a->expires_at->toDateString())->unique()->values()->all());
    }

    public function test_expire_command_only_expires_available_accounts_past_their_date(): void
    {
        $product = Product::factory()->create();
        $past = ['product_id' => $product->id, 'started_at' => today()->subDays(40)->toDateString(), 'active_days' => 28];

        $stale = Account::factory()->create($past);
        $today = Account::factory()->create(['product_id' => $product->id, 'started_at' => today()->subDays(28)->toDateString(), 'active_days' => 28]);
        $fresh = Account::factory()->create(['product_id' => $product->id]);
        $sold = Account::factory()->status(AccountStatus::Sold)->create($past);
        $problem = Account::factory()->status(AccountStatus::Problem)->create($past);

        $this->artisan('accounts:expire')->expectsOutput('1 akun diubah menjadi kedaluwarsa.')->assertSuccessful();

        $this->assertSame(AccountStatus::Expired, $stale->fresh()->status);
        $this->assertSame(AccountStatus::Available, $today->fresh()->status, 'berlaku sampai tanggal kedaluwarsa itu sendiri');
        $this->assertSame(AccountStatus::Available, $fresh->fresh()->status);
        $this->assertSame(AccountStatus::Sold, $sold->fresh()->status);
        $this->assertSame(AccountStatus::Problem, $problem->fresh()->status);

        $this->artisan('accounts:expire')->expectsOutput('0 akun diubah menjadi kedaluwarsa.');
    }

    public function test_expire_command_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command, 'accounts:expire'));

        $this->assertCount(1, $events);
        $this->assertSame('5 0 * * *', $events->first()->expression);
    }

    public function test_can_update_an_account_and_move_it_to_another_product(): void
    {
        $account = Account::factory()->create(['password' => 'lama']);
        $other = Product::factory()->create();

        $this->from('/admin/akun')->put("/admin/akun/{$account->id}", $this->payload($other, ['password' => 'baru', 'status' => 'problem']))
            ->assertRedirect('/admin/akun')
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertSame('baru', $account->password);
        $this->assertSame($other->id, $account->product_id);
        $this->assertSame(AccountStatus::Problem, $account->status);
    }

    public function test_list_rows_carry_everything_the_edit_modal_needs(): void
    {
        $account = Account::factory()->create(['password' => 'abc123', 'note' => 'catatan', 'started_at' => '2026-10-01', 'active_days' => 28]);

        $this->get('/admin/akun')->assertInertia(fn ($page) => $page
            ->where('accounts.data.0.id', $account->id)
            ->where('accounts.data.0.product_id', $account->product_id)
            ->where('accounts.data.0.password', 'abc123')
            ->where('accounts.data.0.note', 'catatan')
            ->where('accounts.data.0.started_at', '2026-10-01')
            ->where('accounts.data.0.active_days', 28)
            ->where('accounts.data.0.expires_at', '2026-10-29')
            ->where('accounts.data.0.created_at', today()->toDateString())
            ->has('products', 1));
    }

    public function test_create_and_edit_pages_are_gone_because_they_are_modals(): void
    {
        $account = Account::factory()->create();

        $this->get('/admin/akun/baru')->assertNotFound();
        $this->get("/admin/akun/{$account->id}/ubah")->assertNotFound();
    }

    public function test_delete_soft_deletes_the_account(): void
    {
        $account = Account::factory()->create();

        $this->delete("/admin/akun/{$account->id}")->assertRedirect();

        $this->assertSoftDeleted($account);
    }

    public function test_sidebar_stock_menu_lists_products_in_order(): void
    {
        $netflix = Product::factory()->create(['name' => 'Netflix', 'slug' => 'netflix', 'sort_order' => 1]);
        Product::factory()->create(['name' => 'Disney', 'slug' => 'disney', 'sort_order' => 2]);
        Account::factory()->count(2)->create(['product_id' => $netflix->id]);
        Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $netflix->id]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('stockMenu', [['name' => 'Netflix', 'slug' => 'netflix'], ['name' => 'Disney', 'slug' => 'disney']]));
    }

    public function test_account_seeder_is_safe_to_run_twice(): void
    {
        $this->seed(\Database\Seeders\ProductSeeder::class);
        $this->seed(\Database\Seeders\AccountSeeder::class);
        $count = Account::count();
        $this->seed(\Database\Seeders\AccountSeeder::class);

        $this->assertGreaterThan(0, $count);
        $this->assertSame($count, Account::count());
    }

    // ---------- aksi massal ----------

    public function test_bulk_status_changes_every_selected_account_and_only_those(): void
    {
        $product = Product::factory()->create();
        [$a, $b, $c] = Account::factory()->count(3)->create(['product_id' => $product->id])->all();

        $this->patch('/admin/akun/massal/status', ['ids' => [$a->id, $b->id], 'status' => 'problem'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', '2 akun diubah menjadi Bermasalah.');

        $this->assertSame([AccountStatus::Problem, AccountStatus::Problem, AccountStatus::Available], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);
    }

    public function test_bulk_sold_records_the_buyer_and_time_on_every_account(): void
    {
        [$a, $b] = Account::factory()->count(2)->create()->all();

        $this->patch('/admin/akun/massal/status', ['ids' => [$a->id, $b->id], 'status' => 'sold', 'sold_to' => 'Dewi 0811'])->assertSessionHasNoErrors();

        foreach ([$a, $b] as $account) {
            $account->refresh();
            $this->assertSame(AccountStatus::Sold, $account->status);
            $this->assertSame('Dewi 0811', $account->sold_to);
            $this->assertNotNull($account->sold_at);
        }
    }

    public function test_bulk_sold_without_a_buyer_keeps_an_existing_buyer(): void
    {
        $has = Account::factory()->status(AccountStatus::Sold)->create(['sold_to' => 'Budi']);
        $fresh = Account::factory()->create();

        $this->patch('/admin/akun/massal/status', ['ids' => [$has->id, $fresh->id], 'status' => 'sold', 'sold_to' => null])->assertSessionHasNoErrors();

        $this->assertSame('Budi', $has->fresh()->sold_to);
        $this->assertNull($fresh->fresh()->sold_to);
        $this->assertSame(AccountStatus::Sold, $fresh->fresh()->status);
    }

    public function test_bulk_back_to_available_clears_the_buyer(): void
    {
        $sold = Account::factory()->status(AccountStatus::Sold)->create(['sold_to' => 'Budi']);

        $this->patch('/admin/akun/massal/status', ['ids' => [$sold->id], 'status' => 'available', 'sold_to' => 'Diabaikan'])->assertSessionHasNoErrors();

        $sold->refresh();
        $this->assertSame([AccountStatus::Available, null, null], [$sold->status, $sold->sold_to, $sold->sold_at]);
    }

    public function test_bulk_validation_rules(): void
    {
        $account = Account::factory()->create();
        $trashed = Account::factory()->create();
        $trashed->delete();

        $cases = [
            ['patch', '/admin/akun/massal/status', ['status' => 'sold'], 'ids'],
            ['patch', '/admin/akun/massal/status', ['ids' => [], 'status' => 'sold'], 'ids'],
            ['patch', '/admin/akun/massal/status', ['ids' => 'semua', 'status' => 'sold'], 'ids'],
            ['patch', '/admin/akun/massal/status', ['ids' => [9999], 'status' => 'sold'], 'ids.0'],
            ['patch', '/admin/akun/massal/status', ['ids' => [$trashed->id], 'status' => 'sold'], 'ids.0'],
            ['patch', '/admin/akun/massal/status', ['ids' => [$account->id, $account->id], 'status' => 'sold'], 'ids.1'],
            ['patch', '/admin/akun/massal/status', ['ids' => [$account->id]], 'status'],
            ['patch', '/admin/akun/massal/status', ['ids' => [$account->id], 'status' => 'ngawur'], 'status'],
            ['patch', '/admin/akun/massal/status', ['ids' => [$account->id], 'status' => 'sold', 'sold_to' => str_repeat('a', 256)], 'sold_to'],
            ['patch', '/admin/akun/massal/status', ['ids' => range(1, 501), 'status' => 'sold'], 'ids'],
            ['delete', '/admin/akun/massal', [], 'ids'],
            ['delete', '/admin/akun/massal', ['ids' => [9999]], 'ids.0'],
        ];

        foreach ($cases as $i => [$method, $url, $data, $field]) {
            try {
                $this->$method($url, $data)->assertSessionHasErrors($field);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail("Kasus #$i harus menolak field '$field': ".$e->getMessage());
            }
        }

        $this->assertSame(AccountStatus::Available, $account->fresh()->status);
        $this->assertNull($account->fresh()->deleted_at);
    }

    public function test_bulk_destroy_soft_deletes_only_the_selected_accounts(): void
    {
        [$a, $b, $keep] = Account::factory()->count(3)->create()->all();

        $this->delete('/admin/akun/massal', ['ids' => [$a->id, $b->id]])->assertSessionHas('success', '2 akun dihapus.');

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertNull($keep->fresh()->deleted_at);
    }

    public function test_the_numeric_account_routes_still_work_next_to_the_bulk_routes(): void
    {
        $account = Account::factory()->create();

        $this->delete("/admin/akun/{$account->id}")->assertSessionHas('success', 'Akun dihapus.');
        $this->assertSoftDeleted($account);
    }
}
