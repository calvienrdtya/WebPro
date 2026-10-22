<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Models\CustomerReport;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@uji.test', 'phone' => '081234567890']);
        $this->product = Product::factory()->create(['name' => 'Netflix Premium']);
        $this->actingAs($this->user);
    }

    private function order(OrderStatus $status, array $attrs = []): Order
    {
        return Order::factory()->status($status)->create(array_merge(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'product_name' => 'Netflix Premium'], $attrs));
    }

    // ---------- halaman dan statistik ----------

    public function test_guests_are_sent_to_login(): void
    {
        auth()->logout();

        $this->get('/profil')->assertRedirect(route('login'));
        $this->post('/laporan')->assertRedirect(route('login'));
        $this->post('/pesanan/PRO1/ulasan')->assertRedirect(route('login'));
    }

    public function test_the_page_shows_identity_stats_and_all_tabs(): void
    {
        $this->order(OrderStatus::Completed, ['total' => 40000]);
        $this->order(OrderStatus::Paid, ['total' => 60000]);
        $this->order(OrderStatus::Pending, ['total' => 99999]);
        Order::factory()->status(OrderStatus::Completed)->create(['total' => 777777]);

        $this->get('/profil')->assertOk()
            ->assertSee('Budi Santoso')->assertSee('budi@uji.test')->assertSee('081234567890')
            ->assertSee('Total Pesanan')->assertSee('Rp100.000', false)->assertDontSee('777.777')
            ->assertSeeInOrder(['Profil', 'Pesanan Saya', 'Transaksi', 'Voucher', 'Laporan', 'Pengaturan']);
    }

    public function test_orders_tab_lists_only_my_orders_with_filters_search_and_pagination(): void
    {
        $this->order(OrderStatus::Completed, ['code' => 'PRO000001AAAAA', 'product_name' => 'Spotify Premium']);
        $this->order(OrderStatus::Verifying, ['code' => 'PRO000002BBBBB']);
        $this->order(OrderStatus::Pending, ['code' => 'PRO000003CCCCC']);
        $this->order(OrderStatus::Expired, ['code' => 'PRO000004DDDDD']);
        Order::factory()->create(['code' => 'PRO000009ZZZZZ']);

        $codes = fn (array $query) => $this->get('/profil?'.http_build_query($query))->assertOk()->viewData('orders')->pluck('code')->all();

        $this->assertSame(['PRO000004DDDDD', 'PRO000003CCCCC', 'PRO000002BBBBB', 'PRO000001AAAAA'], $codes([]));
        $this->assertSame(['PRO000001AAAAA'], $codes(['status' => 'selesai']));
        $this->assertSame(['PRO000002BBBBB'], $codes(['status' => 'diproses']));
        $this->assertSame(['PRO000003CCCCC'], $codes(['status' => 'pending']));
        $this->assertSame(['PRO000004DDDDD'], $codes(['status' => 'gagal']));
        $this->assertSame(['PRO000001AAAAA'], $codes(['q' => 'spotify']));
        $this->assertSame(['PRO000003CCCCC'], $codes(['q' => 'PRO000003']));
        $this->assertSame(4, count($codes(['status' => 'ngawur'])));

        Order::factory()->count(8)->create(['user_id' => $this->user->id, 'product_id' => $this->product->id]);
        $page = $this->get('/profil?tab=pesanan')->viewData('orders');
        $this->assertSame([6, 12], [$page->count(), $page->total()]);
        $this->get('/profil?tab=pesanan&hal_pesanan=2')->assertOk()->assertSee('po-pg--active', false);
    }

    public function test_transactions_tab_summarises_outcomes_and_filters(): void
    {
        $this->order(OrderStatus::Completed, ['code' => 'PRO000001AAAAA']);
        $this->order(OrderStatus::Verifying, ['code' => 'PRO000002BBBBB']);
        $this->order(OrderStatus::Pending, ['code' => 'PRO000003CCCCC']);
        $this->order(OrderStatus::Rejected, ['code' => 'PRO000004DDDDD']);

        $response = $this->get('/profil?tab=transaksi')->assertOk();
        $this->assertEquals(['success' => 1, 'waiting' => 2, 'failed' => 1, 'orders' => 4], collect($response->viewData('stats'))->only(['success', 'waiting', 'failed', 'orders'])->all());

        $codes = fn (string $tx) => $this->get("/profil?tab=transaksi&tx={$tx}")->viewData('transactions')->pluck('code')->sort()->values()->all();
        $this->assertSame(['PRO000001AAAAA'], $codes('selesai'));
        $this->assertSame(['PRO000002BBBBB', 'PRO000003CCCCC'], $codes('menunggu'));
        $this->assertSame(['PRO000004DDDDD'], $codes('gagal'));
        $response->assertSee('Berhasil')->assertSee('Menunggu')->assertSee('QRIS');
    }

    public function test_voucher_tab_groups_claimed_used_and_expired_vouchers(): void
    {
        $avail = Voucher::factory()->create(['code' => 'SIAPPAKAI', 'title' => 'Voucher Siap']);
        $used = Voucher::factory()->create(['code' => 'SUDAHPAKAI', 'title' => 'Voucher Terpakai', 'per_user_limit' => 1]);
        $expired = Voucher::factory()->create(['code' => 'SUDAHBERAKHIR', 'title' => 'Voucher Lama', 'ends_on' => today()->subDay()]);
        Voucher::factory()->create(['code' => 'TIDAKDIKLAIM', 'title' => 'Voucher Orang Lain']);
        foreach ([$avail, $used, $expired] as $v) {
            UserVoucher::create(['user_id' => $this->user->id, 'voucher_id' => $v->id, 'claimed_at' => now()]);
        }
        $this->order(OrderStatus::Completed, ['voucher_id' => $used->id, 'voucher_code' => $used->code, 'voucher_discount' => 1000]);

        $titles = fn (string $vc) => collect($this->get("/profil?tab=voucher&vc={$vc}")->assertOk()->viewData('vouchers')['items'])->pluck('title')->all();

        $this->assertSame(['Voucher Siap'], $titles('avail'));
        $this->assertSame(['Voucher Terpakai'], $titles('used'));
        $this->assertSame(['Voucher Lama'], $titles('expired'));
        $this->get('/profil?tab=voucher')->assertSee('Siap Dipakai')->assertDontSee('Voucher Orang Lain');
        $this->get('/profil?tab=voucher&vc=used')->assertSee('Sudah Dipakai');
    }

    // ---------- ubah profil, sandi, hapus akun ----------

    public function test_name_and_whatsapp_can_be_updated_but_email_cannot(): void
    {
        $this->patch('/profil', ['name' => 'Budi Baru', 'phone' => '0812-9999-0000', 'email' => 'lain@uji.test'])->assertRedirect(route('profil', ['tab' => 'profil']));

        $this->user->refresh();
        $this->assertSame(['Budi Baru', '0812-9999-0000', 'budi@uji.test'], [$this->user->name, $this->user->phone, $this->user->email]);

        $this->patch('/profil', ['name' => '', 'phone' => 'abc'])->assertSessionHasErrors(['name', 'phone']);
        $this->patch('/profil', ['name' => 'Tanpa WA', 'phone' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->user->fresh()->phone);
    }

    public function test_password_change_checks_the_current_password_and_the_rules(): void
    {
        $this->user->update(['password' => 'LamaSandi123']);

        $this->patch('/profil/sandi', ['current_password' => 'salah', 'password' => 'BaruSandi456', 'password_confirmation' => 'BaruSandi456'])->assertSessionHasErrors('current_password');
        $this->patch('/profil/sandi', ['current_password' => 'LamaSandi123', 'password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->patch('/profil/sandi', ['current_password' => 'LamaSandi123', 'password' => 'BaruSandi456', 'password_confirmation' => 'beda'])->assertSessionHasErrors('password');

        $this->patch('/profil/sandi', ['current_password' => 'LamaSandi123', 'password' => 'BaruSandi456', 'password_confirmation' => 'BaruSandi456'])->assertSessionHas('success');
        $this->assertTrue(password_verify('BaruSandi456', $this->user->fresh()->password));
    }

    public function test_a_google_only_account_can_create_a_password_without_a_current_one(): void
    {
        $google = User::factory()->create(['google_id' => 'g-1']);
        $google->forceFill(['password' => null])->save();

        $this->actingAs($google)->patch('/profil/sandi', ['password' => 'SandiBaru123', 'password_confirmation' => 'SandiBaru123'])->assertSessionHas('success');

        $this->assertNotNull($google->fresh()->password);
    }

    public function test_deleting_the_account_anonymises_the_user_and_keeps_the_orders(): void
    {
        $this->user->update(['password' => 'Sandi12345', 'google_id' => 'g-9']);
        $order = $this->order(OrderStatus::Completed);
        $voucher = Voucher::factory()->create();
        UserVoucher::create(['user_id' => $this->user->id, 'voucher_id' => $voucher->id, 'claimed_at' => now()]);

        $this->delete('/profil', ['confirm' => 'hapus', 'password' => 'Sandi12345'])->assertSessionHasErrors('confirm');
        $this->delete('/profil', ['confirm' => 'HAPUS', 'password' => 'salah'])->assertSessionHasErrors('password');
        $this->assertSame('Budi Santoso', $this->user->fresh()->name);

        $this->delete('/profil', ['confirm' => 'HAPUS', 'password' => 'Sandi12345'])->assertRedirect(route('home'));

        $this->assertGuest();
        $user = User::findOrFail($this->user->id);
        $this->assertSame('Pengguna Terhapus', $user->name);
        $this->assertStringEndsWith('@proakses.invalid', $user->email);
        $this->assertNull($user->phone);
        $this->assertNull($user->google_id);
        $this->assertTrue($user->isBlocked());
        $this->assertSame(0, UserVoucher::where('user_id', $user->id)->count());
        $this->assertSame($user->id, $order->fresh()->user_id, 'riwayat pesanan tetap ada');
        $this->assertSame('Budi Santoso', $order->fresh()->customer_name === '' ? '' : 'Budi Santoso');
    }

    public function test_the_account_cannot_be_deleted_with_unpaid_orders_or_as_an_admin(): void
    {
        $this->user->update(['password' => 'Sandi12345']);
        $this->order(OrderStatus::Pending);

        $this->delete('/profil', ['confirm' => 'HAPUS', 'password' => 'Sandi12345'])->assertSessionHas('error');
        $this->assertSame('Budi Santoso', $this->user->fresh()->name);

        $admin = User::factory()->create(['is_admin' => true, 'password' => 'Sandi12345']);
        $this->actingAs($admin)->delete('/profil', ['confirm' => 'HAPUS', 'password' => 'Sandi12345'])->assertSessionHas('error');
        $this->assertNotSame('Pengguna Terhapus', $admin->fresh()->name);
    }

    // ---------- ulasan ----------

    public function test_a_review_can_be_written_once_for_a_paid_order_and_waits_for_approval(): void
    {
        $order = $this->order(OrderStatus::Completed);

        $this->post("/pesanan/{$order->code}/ulasan", ['rating' => 5, 'body' => 'Akunnya lancar dan cepat sekali dikirim.'])
            ->assertRedirect(route('profil', ['tab' => 'pesanan']))->assertSessionHas('success');

        $review = Review::firstOrFail();
        $this->assertSame([$this->product->id, $this->user->id, $order->id, 'Budi Santoso', 5, 'pending'], [$review->product_id, $review->user_id, $review->order_id, $review->reviewer_name, $review->rating, $review->status]);
        $this->assertSame(0, $this->product->reviewSummary()['count'], 'belum disetujui, belum dihitung');

        $this->post("/pesanan/{$order->code}/ulasan", ['rating' => 1, 'body' => 'Ulasan kedua tidak boleh masuk.'])->assertSessionHas('info');
        $this->assertSame(1, Review::count());
        $this->get('/profil?tab=pesanan')->assertSee('Ulasan 5/5')->assertSee('menunggu persetujuan');
    }

    public function test_review_rules_ownership_status_and_validation(): void
    {
        $paid = $this->order(OrderStatus::Paid);
        $unpaid = $this->order(OrderStatus::Pending);
        $foreign = Order::factory()->status(OrderStatus::Completed)->create();

        $this->post("/pesanan/{$foreign->code}/ulasan", ['rating' => 5, 'body' => 'Bukan pesanan saya sendiri.'])->assertNotFound();
        $this->post("/pesanan/{$unpaid->code}/ulasan", ['rating' => 5, 'body' => 'Belum dibayar tetapi mau diulas.'])->assertSessionHas('error');
        $this->post("/pesanan/{$paid->code}/ulasan", ['body' => 'Tanpa bintang sama sekali.'])->assertSessionHasErrors('rating');
        $this->post("/pesanan/{$paid->code}/ulasan", ['rating' => 6, 'body' => 'Bintang kebanyakan sekali.'])->assertSessionHasErrors('rating');
        $this->post("/pesanan/{$paid->code}/ulasan", ['rating' => 4, 'body' => 'pendek'])->assertSessionHasErrors('body');
        $this->assertSame(0, Review::count());
    }

    public function test_review_buttons_show_only_for_paid_orders_without_a_review(): void
    {
        $paid = $this->order(OrderStatus::Completed, ['code' => 'PRO000001AAAAA']);
        $this->order(OrderStatus::Pending, ['code' => 'PRO000002BBBBB']);

        $html = $this->get('/profil?tab=pesanan')->getContent();
        $this->assertSame(1, preg_match_all('/Tulis Ulasan\s*<\/button>/', $html));

        Review::factory()->create(['order_id' => $paid->id, 'user_id' => $this->user->id, 'product_id' => $this->product->id]);
        $this->assertSame(0, preg_match_all('/Tulis Ulasan\s*<\/button>/', $this->get('/profil?tab=pesanan')->getContent()));
    }

    // ---------- laporan ----------

    private function reportData(array $override = []): array
    {
        return array_merge(['category' => 'account', 'subject' => 'Akun tidak bisa login', 'body' => 'Sejak tadi pagi akun saya tidak bisa dipakai login.'], $override);
    }

    public function test_report_page_lists_own_orders_preselects_from_the_order_code_and_needs_login(): void
    {
        $order = $this->order(OrderStatus::Completed);
        $foreign = Order::factory()->create();

        $this->get('/lapor-masalah?pesanan='.$order->code)->assertOk()
            ->assertViewHas('selectedOrder', $order->id)
            ->assertSee('Lapor Masalah')->assertSee($order->code)->assertDontSee($foreign->code);
        $this->get('/lapor-masalah?pesanan='.$foreign->code)->assertViewHas('selectedOrder', null);
        $this->get('/profil?tab=laporan')->assertSee(route('reports.create'));

        auth()->logout();
        $this->get('/lapor-masalah')->assertRedirect(route('login'));
    }

    public function test_a_report_can_be_sent_with_a_related_order_and_images(): void
    {
        Storage::fake('local');
        Storage::fake('uploads');
        $order = $this->order(OrderStatus::Completed);

        $this->post('/laporan', $this->reportData(['order_id' => $order->id, 'attachments' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.jpg')]]))
            ->assertRedirect(route('profil', ['tab' => 'laporan']))->assertSessionHas('success');

        $report = CustomerReport::firstOrFail();
        $this->assertSame([$this->user->id, 'account', $order->id, CustomerReport::OPEN], [$report->user_id, $report->category->value, $report->order_id, $report->status]);
        $this->assertSame(2, $report->attachments()->count());
        foreach ($report->attachments as $a) {
            // Penyimpanan privat, bukan folder publik.
            Storage::disk('local')->assertExists($a->path);
            Storage::disk('uploads')->assertMissing($a->path);
        }

        $this->get('/profil?tab=laporan')->assertSee('Akun tidak bisa login')->assertSee('Menunggu')->assertDontSee('Catatan internal');
    }

    public function test_report_validation(): void
    {
        $foreign = Order::factory()->create();

        $this->post('/laporan', [])->assertSessionHasErrors(['category', 'subject', 'body']);
        $this->post('/laporan', $this->reportData(['category' => 'ngawur']))->assertSessionHasErrors('category');
        $this->post('/laporan', $this->reportData(['order_id' => $foreign->id]))->assertSessionHasErrors('order_id');
        $this->post('/laporan', $this->reportData(['body' => 'pendek']))->assertSessionHasErrors('body');
        $this->post('/laporan', $this->reportData(['body' => 'Akun tidak bisa']))->assertSessionHasErrors('body');
        $this->post('/laporan', $this->reportData(['subject' => 'Akun']))->assertSessionHasErrors('subject');
        $this->post('/laporan', $this->reportData(['attachments' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]]))->assertSessionHasErrors('attachments.0');
        $this->post('/laporan', $this->reportData(['attachments' => [UploadedFile::fake()->image('1.png'), UploadedFile::fake()->image('2.png'), UploadedFile::fake()->image('3.png'), UploadedFile::fake()->image('4.png')]]))->assertSessionHasErrors('attachments');
        $this->assertSame(0, CustomerReport::count());
    }

    public function test_report_limits_open_reports_and_reports_per_day(): void
    {
        CustomerReport::factory()->count(3)->create(['user_id' => $this->user->id, 'created_at' => now()->subDays(2)]);
        $this->post('/laporan', $this->reportData())->assertRedirect(route('reports.create'))->assertSessionHas('error');
        $this->assertSame(3, CustomerReport::count());

        CustomerReport::query()->delete();
        CustomerReport::factory()->count(5)->resolved()->create(['user_id' => $this->user->id]);
        $this->post('/laporan', $this->reportData())->assertSessionHas('error');
        $this->assertSame(5, CustomerReport::count());

        CustomerReport::query()->update(['created_at' => now()->subDay()]);
        $this->post('/laporan', $this->reportData())->assertSessionHas('success');
    }

    public function test_the_customer_only_sees_waiting_or_done_never_the_admin_note(): void
    {
        $open = CustomerReport::factory()->create(['user_id' => $this->user->id, 'subject' => 'Laporan masih terbuka']);
        $done = CustomerReport::factory()->resolved()->create(['user_id' => $this->user->id, 'subject' => 'Laporan sudah beres']);
        $done->forceFill(['resolution_note' => 'Catatan rahasia admin'])->save();
        CustomerReport::factory()->create(['subject' => 'Laporan orang lain']);

        $this->get('/profil?tab=laporan')->assertSee('Laporan masih terbuka')->assertSee('Laporan sudah beres')
            ->assertSee('Menunggu')->assertSee('Selesai')->assertDontSee('Catatan rahasia admin')->assertDontSee('Laporan orang lain');
    }
}
