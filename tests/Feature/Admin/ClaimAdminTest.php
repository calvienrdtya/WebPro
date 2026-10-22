<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\ReportCategory;
use App\Enums\ReportResolution;
use App\Mail\AccountDeliveredMail;
use App\Models\Account;
use App\Models\CustomerReport;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\Product;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClaimAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        Mail::fake();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->product = Product::factory()->create(['name' => 'Netflix Premium']);
        $this->actingAs($this->admin);
    }

    /** Pesanan lunas dengan satu akun terkirim; garansi berakhir $warrantyDays dari hari ini (negatif = sudah habis, null = tanpa garansi). */
    private function delivered(?int $warrantyDays = 10, ?User $user = null, array $order = []): array
    {
        $account = Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $this->product->id, 'email' => 'lama@contoh.test', 'cogs' => 15000]);
        $o = Order::factory()->status(OrderStatus::Completed)->create(array_merge([
            'user_id' => ($user ?? User::factory()->create())->id, 'product_id' => $this->product->id, 'customer_name' => 'Budi Santoso', 'customer_email' => 'budi@uji.test',
            'warranty_name' => $warrantyDays === null ? null : 'Garansi Penuh', 'warranty_days' => $warrantyDays === null ? null : 28, 'paid_at' => now()->subDays(5),
        ], $order));
        $d = $o->deliveries()->create([
            'sequence' => 1, 'account_id' => $account->id, 'cogs' => 15000, 'delivered_at' => now()->subDays(5),
            'warranty_ends_on' => $warrantyDays === null ? null : today()->addDays($warrantyDays)->toDateString(),
        ]);

        return [$o, $d, $account];
    }

    private function stock(array $attrs = []): Account
    {
        return Account::factory()->create(array_merge(['product_id' => $this->product->id, 'email' => 'baru@contoh.test', 'cogs' => 0], $attrs));
    }

    private function replace(Order $o, OrderDelivery $d, Account $new, array $extra = [])
    {
        return $this->post("/admin/pesanan/{$o->id}/ganti-akun", array_merge(['delivery_id' => $d->id, 'account_id' => $new->id, 'reason' => 'backfree'], $extra));
    }

    public function test_guests_and_regular_users_cannot_reach_claim_routes(): void
    {
        [$o, $d] = $this->delivered();
        $claim = WarrantyClaim::create(['order_id' => $o->id, 'source' => 'order']);
        $report = CustomerReport::factory()->create();
        $routes = [
            ['get', '/admin/klaim-garansi'], ['patch', "/admin/klaim-garansi/{$claim->id}/batal"], ['post', "/admin/laporan/{$report->id}/klaim"],
            ['get', "/admin/pesanan/{$o->id}/pengganti"], ['post', "/admin/pesanan/{$o->id}/ganti-akun"],
            ['get', '/admin/bengkel'], ['patch', "/admin/bengkel/{$claim->id}/stok"],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
    }

    // ---------- ganti akun ----------

    public function test_replacing_swaps_the_account_records_the_claim_and_emails_the_new_credentials(): void
    {
        [$o, $d, $old] = $this->delivered(10);
        $new = $this->stock(['email' => 'pengganti@contoh.test', 'password' => 'baru123']);

        $this->replace($o, $d, $new, ['note' => 'Profil terkunci'])->assertSessionHas('success');

        $d->refresh();
        $this->assertSame($new->id, $d->account_id);
        $this->assertSame(15000, $d->cogs, 'HPP kiriman tetap HPP akun awal');
        $this->assertSame(today()->addDays(10)->toDateString(), $d->warranty_ends_on->toDateString(), 'masa garansi tidak berubah');
        $this->assertSame(AccountStatus::Problem, $old->fresh()->status);
        $this->assertStringContainsString('Profil terkunci', $old->fresh()->note);
        $this->assertSame(AccountStatus::Sold, $new->fresh()->status);
        $this->assertStringContainsString($o->code, $new->fresh()->sold_to);

        $claim = WarrantyClaim::firstOrFail();
        $this->assertSame(['done', 'stock', 'order', $o->id, $old->id, $new->id, false, 0], [$claim->status, $claim->mode, $claim->source, $claim->order_id, $claim->old_account_id, $claim->new_account_id, $claim->outside_warranty, $claim->claim_cost]);
        $this->assertSame($this->admin->id, $claim->handled_by);

        Mail::assertQueued(AccountDeliveredMail::class, fn ($m) => $m->replacement && $m->hasTo('budi@uji.test')
            && str_contains($m->render(), 'pengganti@contoh.test') && str_contains($m->render(), 'baru123') && str_contains($m->envelope()->subject, 'pengganti'));
    }

    public function test_claim_cost_is_not_recorded_yet_even_for_a_paid_replacement_account(): void
    {
        [$o, $d] = $this->delivered();

        $this->replace($o, $d, $this->stock(['cogs' => 12000]));

        $this->assertSame(0, WarrantyClaim::firstOrFail()->claim_cost);
    }

    public function test_a_claim_can_be_made_repeatedly_while_the_warranty_is_active(): void
    {
        [$o, $d] = $this->delivered();

        $this->replace($o, $d, $this->stock())->assertSessionHas('success');
        $this->replace($o, $d->fresh(), $this->stock(['email' => 'ketiga@contoh.test']))->assertSessionHas('success');

        $this->assertSame(2, WarrantyClaim::where('order_id', $o->id)->count());
        $this->assertSame(2, Account::where('status', AccountStatus::Problem)->count());
    }

    public function test_replacing_after_the_warranty_ended_needs_an_explicit_confirmation(): void
    {
        [$o, $d, $old] = $this->delivered(-3);
        $new = $this->stock();

        $this->replace($o, $d, $new)->assertSessionHas('error');
        $this->assertSame(0, WarrantyClaim::count());
        $this->assertSame(AccountStatus::Sold, $old->fresh()->status);

        $this->replace($o, $d, $new, ['confirm_outside' => true])->assertSessionHas('success');
        $this->assertTrue(WarrantyClaim::firstOrFail()->outside_warranty);
    }

    public function test_an_order_without_warranty_can_also_be_replaced_with_confirmation(): void
    {
        [$o, $d] = $this->delivered(null);

        $this->replace($o, $d, $this->stock())->assertSessionHas('error');
        $this->replace($o, $d, $this->stock(), ['confirm_outside' => true])->assertSessionHas('success');
    }

    public function test_unusable_replacement_accounts_are_refused(): void
    {
        [$o, $d, $old] = $this->delivered();
        $otherProduct = Account::factory()->create(['product_id' => Product::factory()->create()->id]);
        $sold = Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $this->product->id]);
        $expired = $this->stock(['started_at' => today()->subDays(40)->toDateString(), 'active_days' => 28]);

        foreach ([$otherProduct, $sold, $expired] as $bad) {
            $this->replace($o, $d, $bad)->assertSessionHas('error', 'Akun pengganti sudah tidak tersedia. Pilih akun lain.');
        }
        $this->post("/admin/pesanan/{$o->id}/ganti-akun", ['delivery_id' => $d->id, 'account_id' => 99999, 'reason' => 'backfree'])->assertSessionHas('error');
        $this->assertSame(0, WarrantyClaim::count());
        $this->assertSame($old->id, $d->fresh()->account_id);
    }

    public function test_validation_and_delivery_ownership(): void
    {
        [$o, $d] = $this->delivered();
        [, $otherDelivery] = $this->delivered();
        $new = $this->stock();

        $this->post("/admin/pesanan/{$o->id}/ganti-akun", [])->assertSessionHasErrors(['delivery_id', 'account_id', 'reason']);
        $this->replace($o, $d, $new, ['reason' => 'ngawur'])->assertSessionHasErrors('reason');
        $this->post("/admin/pesanan/{$o->id}/ganti-akun", ['delivery_id' => $otherDelivery->id, 'account_id' => $new->id, 'reason' => 'other'])->assertSessionHasErrors('delivery_id');
    }

    public function test_an_unpaid_order_cannot_be_replaced(): void
    {
        $o = Order::factory()->status(OrderStatus::Verifying)->create(['product_id' => $this->product->id]);

        $this->get("/admin/pesanan/{$o->id}/pengganti")->assertNotFound();
    }

    public function test_replacement_options_list_stock_by_lowest_cogs_first_and_describe_the_warranty(): void
    {
        [$o, $d] = $this->delivered(10);
        $this->stock(['email' => 'mahal@contoh.test', 'cogs' => 20000]);
        $this->stock(['email' => 'gratis@contoh.test', 'cogs' => 0]);
        $this->stock(['email' => 'mati@contoh.test', 'cogs' => 0, 'started_at' => today()->subDays(40)->toDateString(), 'active_days' => 28]);
        Account::factory()->create(['product_id' => Product::factory()->create()->id, 'email' => 'produklain@contoh.test']);

        $this->getJson("/admin/pesanan/{$o->id}/pengganti")->assertOk()
            ->assertJsonPath('candidates.0.email', 'gratis@contoh.test')->assertJsonPath('candidates.1.email', 'mahal@contoh.test')
            ->assertJsonCount(2, 'candidates')
            ->assertJsonPath('deliveries.0.within_warranty', true)->assertJsonPath('deliveries.0.account.email', 'lama@contoh.test')
            ->assertJsonPath('order.age_days', 5)->assertJsonPath('open_claim_id', null);
    }

    // ---------- mode replacement (dipakai bersama) ----------

    /** Akun terjual lewat pesanan lain dengan garansi sudah habis ($warrantyDays negatif) atau tanpa garansi (null). */
    private function sharedSource(?int $warrantyDays, string $email = 'bekas@contoh.test'): Account
    {
        [, , $account] = $this->delivered($warrantyDays);
        $account->update(['email' => $email]);

        return $account;
    }

    public function test_replacement_candidates_are_sold_accounts_whose_users_have_no_warranty_left(): void
    {
        [$o, $d, $mine] = $this->delivered(10);
        $this->sharedSource(-3, 'garansi-habis@contoh.test');
        $this->sharedSource(null, 'tanpa-garansi@contoh.test');
        $this->sharedSource(5, 'garansi-aktif@contoh.test');
        Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $this->product->id, 'email' => 'manual@contoh.test']);
        $expired = $this->sharedSource(-3, 'kedaluwarsa@contoh.test');
        $expired->forceFill(['started_at' => today()->subDays(60)->toDateString()])->save();
        $problem = $this->sharedSource(-3, 'rusak@contoh.test');
        $problem->forceFill(['status' => AccountStatus::Problem])->save();

        $emails = collect($this->getJson("/admin/pesanan/{$o->id}/pengganti")->assertOk()->json('replacements'))->pluck('email')->sort()->values()->all();

        $this->assertSame(['garansi-habis@contoh.test', 'tanpa-garansi@contoh.test'], $emails);
        $this->assertNotContains('lama@contoh.test', $emails, 'akun milik pesanan itu sendiri tidak ikut');
    }

    public function test_replacing_in_replacement_mode_shares_the_account_without_taking_it_from_its_owner(): void
    {
        [$o, $d, $old] = $this->delivered(10);
        $source = $this->sharedSource(-3, 'bekas@contoh.test');

        $this->replace($o, $d, $source, ['mode' => 'replacement', 'note' => 'dipakai bersama'])->assertSessionHas('success');

        $d->refresh();
        $this->assertSame($source->id, $d->account_id);
        $this->assertSame(AccountStatus::Sold, $source->fresh()->status);
        $this->assertNull($source->fresh()->sold_to, 'atas nama pemakai awal, tidak ditimpa');
        $this->assertSame($source->id, $source->deliveries()->orderBy('id')->first()->account_id, 'pemakai awal tetap');
        $this->assertSame(2, $source->deliveries()->count());
        $this->assertStringContainsString($o->code, $source->fresh()->note);
        $this->assertSame(AccountStatus::Problem, $old->fresh()->status);

        $claim = WarrantyClaim::firstOrFail();
        $this->assertSame(['done', 'replacement', 0], [$claim->status, $claim->mode, $claim->claim_cost]);
        Mail::assertQueued(AccountDeliveredMail::class, fn ($m) => $m->replacement && str_contains($m->render(), 'bekas@contoh.test'));

        // Pemakai barunya punya garansi aktif, jadi akun ini tidak ditawarkan lagi ke pelanggan lain.
        [$o2] = $this->delivered(10);
        $this->assertNotContains('bekas@contoh.test', collect($this->getJson("/admin/pesanan/{$o2->id}/pengganti")->json('replacements'))->pluck('email')->all());
    }

    public function test_replacement_mode_refuses_accounts_that_are_not_candidates(): void
    {
        [$o, $d] = $this->delivered(10);
        $stock = $this->stock();
        $active = $this->sharedSource(5, 'garansi-aktif@contoh.test');

        foreach ([$stock, $active] as $bad) {
            $this->replace($o, $d, $bad, ['mode' => 'replacement'])->assertSessionHas('error', 'Akun pengganti sudah tidak tersedia. Pilih akun lain.');
        }
        $this->replace($o, $d, $this->sharedSource(-3), ['mode' => 'ngawur'])->assertSessionHasErrors('mode');
        $this->assertSame(0, WarrantyClaim::count());
        $this->assertSame(AccountStatus::Available, $stock->fresh()->status);
    }

    public function test_a_shared_source_cannot_be_used_in_stock_mode(): void
    {
        [$o, $d] = $this->delivered(10);

        $this->replace($o, $d, $this->sharedSource(-3))->assertSessionHas('error');
        $this->assertSame(0, WarrantyClaim::count());
    }

    // ---------- dari laporan ----------

    private function report(User $user, ReportCategory $category = ReportCategory::Account): CustomerReport
    {
        return CustomerReport::factory()->create(['user_id' => $user->id, 'category' => $category->value, 'subject' => 'Akun tidak bisa login']);
    }

    public function test_an_account_report_can_become_a_claim_then_replacing_resolves_the_report(): void
    {
        $user = User::factory()->create();
        [$o, $d, $old] = $this->delivered(10, $user);
        $report = $this->report($user);

        $this->post("/admin/laporan/{$report->id}/klaim", ['order_id' => $o->id])->assertSessionHas('success');
        $claim = WarrantyClaim::firstOrFail();
        $this->assertSame(['open', 'report', $report->id], [$claim->status, $claim->source, $claim->report_id]);
        $this->assertTrue($report->fresh()->isOpen(), 'laporan baru selesai setelah akun diganti');
        $this->post("/admin/laporan/{$report->id}/klaim", ['order_id' => $o->id])->assertSessionHas('error');

        $this->getJson("/admin/pesanan/{$o->id}/pengganti")->assertJsonPath('open_claim_id', $claim->id);
        $this->replace($o, $d, $this->stock(), ['claim_id' => $claim->id])->assertSessionHas('success');

        $this->assertSame(1, WarrantyClaim::count(), 'klaim terbuka dilengkapi, bukan dibuat ganda');
        $this->assertSame('done', $claim->fresh()->status);
        $report->refresh();
        $this->assertSame([CustomerReport::RESOLVED, ReportResolution::AccountReplaced], [$report->status, $report->resolution]);
    }

    public function test_only_account_reports_with_an_active_warranty_of_the_reporter_can_be_moved(): void
    {
        $user = User::factory()->create();
        [$ok] = $this->delivered(10, $user);
        [$expired] = $this->delivered(-1, $user);
        [$none] = $this->delivered(null, $user);
        [$stranger] = $this->delivered(10);
        $account = $this->report($user);
        $question = $this->report($user, ReportCategory::Question);

        $this->post("/admin/laporan/{$question->id}/klaim", ['order_id' => $ok->id])->assertSessionHas('error');
        foreach ([$expired, $none, $stranger] as $bad) {
            $this->post("/admin/laporan/{$account->id}/klaim", ['order_id' => $bad->id])->assertSessionHas('error');
        }
        $this->post("/admin/laporan/{$account->id}/klaim", ['order_id' => 99999])->assertSessionHasErrors('order_id');
        $this->assertSame(0, WarrantyClaim::count());

        $account->resolve(ReportResolution::Other, null, null, $this->admin);
        $this->post("/admin/laporan/{$account->id}/klaim", ['order_id' => $ok->id])->assertSessionHas('error');
    }

    public function test_report_list_exposes_the_reporters_orders_and_the_claim(): void
    {
        $user = User::factory()->create();
        [$ok] = $this->delivered(10, $user);
        [$expired] = $this->delivered(-1, $user);
        $report = $this->report($user);

        $this->get('/admin/laporan')->assertInertia(fn ($page) => $page
            ->has('reports.data.0.orders', 2)
            ->where('reports.data.0.orders', fn ($orders) => collect($orders)->firstWhere('code', $ok->code)['warranty_open'] === true
                && collect($orders)->firstWhere('code', $expired->code)['warranty_open'] === false)
            ->where('reports.data.0.claim', null));

        $this->post("/admin/laporan/{$report->id}/klaim", ['order_id' => $ok->id]);
        $this->get('/admin/laporan')->assertInertia(fn ($page) => $page->where('reports.data.0.claim.status', 'open'));
    }

    // ---------- daftar klaim ----------

    public function test_claim_list_filters_cancels_and_summarises(): void
    {
        $user = User::factory()->create();
        [$o1, $d1] = $this->delivered(10, $user);
        [$o2] = $this->delivered(10, $user);
        $this->replace($o1, $d1, $this->stock());
        $report = $this->report($user);
        $this->post("/admin/laporan/{$report->id}/klaim", ['order_id' => $o2->id]);
        $open = WarrantyClaim::where('status', 'open')->firstOrFail();

        $this->get('/admin/klaim-garansi')->assertInertia(fn ($page) => $page
            ->where('filters.status', 'all')->has('claims.data', 2)->where('claims.data.0.status', 'open')
            ->where('counts', ['all' => 2, 'open' => 1, 'done' => 1, 'cancelled' => 0])
            ->where('stats', ['total' => 1, 'waiting' => 1, 'this_month' => 1]));
        $this->get('/admin/klaim-garansi?status=done&q='.$o1->code)->assertInertia(fn ($page) => $page->has('claims.data', 1)->where('claims.data.0.old_account.email', 'lama@contoh.test'));
        $this->get('/admin/klaim-garansi?status=ngawur')->assertInertia(fn ($page) => $page->where('filters.status', 'all'));

        $this->patch("/admin/klaim-garansi/{$open->id}/batal")->assertSessionHas('success');
        $this->assertSame('cancelled', $open->fresh()->status);
        $this->patch("/admin/klaim-garansi/{$open->id}/batal")->assertSessionHas('error');
        $this->assertTrue($report->fresh()->isOpen());
    }

    public function test_order_list_exposes_claim_history(): void
    {
        [$o, $d] = $this->delivered();
        $this->replace($o, $d, $this->stock(['email' => 'baru@contoh.test']));

        $this->get('/admin/pesanan')->assertInertia(fn ($page) => $page
            ->where('orders.data.0.claims.0.old_email', 'lama@contoh.test')->where('orders.data.0.claims.0.new_email', 'baru@contoh.test')
            ->where('orders.data.0.claims.0.status', 'done'));
    }

    // ---------- bengkel ----------

    public function test_workshop_lists_replaced_accounts_and_restocking_returns_them_to_the_pool(): void
    {
        [$o, $d, $old] = $this->delivered();
        $this->replace($o, $d, $this->stock());
        $claim = WarrantyClaim::firstOrFail();

        $this->get('/admin/bengkel')->assertInertia(fn ($page) => $page
            ->where('counts', ['all' => 1, 'workshop' => 1, 'out' => 0])
            ->where('items.data.0.account.email', 'lama@contoh.test')->where('items.data.0.in_workshop', true)
            ->where('items.data.0.reason', 'Backfree')->where('items.data.0.order_code', $o->code));

        $this->patch("/admin/bengkel/{$claim->id}/stok")->assertSessionHas('success');
        $this->assertSame(AccountStatus::Available, $old->fresh()->status);
        $this->get('/admin/bengkel?status=out')->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.in_workshop', false));
        $this->get('/admin/bengkel?status=workshop')->assertInertia(fn ($page) => $page->has('items.data', 0));
        $this->patch("/admin/bengkel/{$claim->id}/stok")->assertSessionHas('error');
    }

    public function test_an_expired_account_cannot_be_restocked_and_search_finds_by_email(): void
    {
        [$o, $d, $old] = $this->delivered();
        $this->replace($o, $d, $this->stock());
        $claim = WarrantyClaim::firstOrFail();
        $old->forceFill(['started_at' => today()->subDays(60)->toDateString()])->save();

        $this->patch("/admin/bengkel/{$claim->id}/stok")->assertSessionHas('error');
        $this->assertSame(AccountStatus::Problem, $old->fresh()->status);
        $this->get('/admin/bengkel?q=lama@contoh')->assertInertia(fn ($page) => $page->has('items.data', 1));
        $this->get('/admin/bengkel?q=tidakada')->assertInertia(fn ($page) => $page->has('items.data', 0));
    }
}
