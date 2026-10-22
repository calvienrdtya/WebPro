<?php

namespace Tests\Feature\Admin;

use App\Enums\ReportCategory;
use App\Enums\ReportResolution;
use App\Mail\NewReportMail;
use App\Models\CustomerReport;
use App\Models\ReportAttachment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    // ---------- akses ----------

    public function test_guests_and_regular_users_cannot_reach_report_or_setting_routes(): void
    {
        $report = CustomerReport::factory()->create();
        $routes = [
            ['get', '/admin/laporan'], ['patch', "/admin/laporan/{$report->id}/selesai"], ['patch', "/admin/laporan/{$report->id}/buka"],
            ['delete', "/admin/laporan/{$report->id}"], ['patch', '/admin/laporan/massal/selesai'], ['delete', '/admin/laporan/massal'],
            ['get', '/admin/pengaturan'], ['put', '/admin/pengaturan/notifikasi'],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertTrue($report->fresh()->isOpen());
    }

    // ---------- daftar ----------

    public function test_index_sorts_oldest_first_by_default_newest_first_on_request_and_counts_by_status(): void
    {
        $newer = CustomerReport::factory()->create(['subject' => 'Baru', 'created_at' => now()->subHour()]);
        $done = CustomerReport::factory()->resolved()->create(['subject' => 'Selesai', 'created_at' => now()->subDays(2)]);
        $older = CustomerReport::factory()->create(['subject' => 'Lama', 'created_at' => now()->subDay()]);

        $this->get('/admin/laporan?sort=desc')->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'desc')
            ->where('reports.data.0.id', $newer->id)->where('reports.data.1.id', $older->id)->where('reports.data.2.id', $done->id));

        $this->get('/admin/laporan?sort=ngawur')->assertInertia(fn ($page) => $page->where('filters.sort', 'asc'));

        $this->get('/admin/laporan')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('filters.sort', 'asc')
            ->where('reports.data.0.id', $done->id)
            ->where('reports.data.1.id', $older->id)
            ->where('reports.data.2.id', $newer->id)
            ->where('counts', ['all' => 3, 'open' => 2, 'resolved' => 1])
            ->where('stats.waiting', 2)
            ->where('stats.resolved_this_month', 1));
    }

    public function test_index_filters_and_searches(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@uji.test']);
        CustomerReport::factory()->create(['user_id' => $budi->id, 'subject' => 'Netflix mati', 'category' => ReportCategory::Account]);
        // Nama tetap: nama acak Faker bisa kebetulan memuat kata pencarian ("Budiman" cocok "budi").
        $siti = User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@contoh.test']);
        $rina = User::factory()->create(['name' => 'Rina Kusuma', 'email' => 'rina@contoh.test']);
        CustomerReport::factory()->create(['user_id' => $siti->id, 'subject' => 'Belum bayar', 'category' => ReportCategory::Payment]);
        CustomerReport::factory()->resolved()->create(['user_id' => $rina->id, 'subject' => 'Sudah beres']);

        $this->get('/admin/laporan?q=budi')->assertInertia(fn ($page) => $page->has('reports.data', 1)->where('reports.data.0.subject', 'Netflix mati'));
        $this->get('/admin/laporan?q=Belum%20bayar')->assertInertia(fn ($page) => $page->has('reports.data', 1));
        $this->get('/admin/laporan?category=payment')->assertInertia(fn ($page) => $page->has('reports.data', 1)->where('reports.data.0.subject', 'Belum bayar'));
        $this->get('/admin/laporan?status=resolved')->assertInertia(fn ($page) => $page->has('reports.data', 1)->where('reports.data.0.subject', 'Sudah beres'));
        $this->get('/admin/laporan?status=ngawur&category=ngawur')->assertInertia(fn ($page) => $page->has('reports.data', 3));
    }

    public function test_index_shows_the_invoice_of_the_related_order_and_searches_by_it(): void
    {
        $order = \App\Models\Order::factory()->create(['code' => 'PRO20260917AAAAA']);
        CustomerReport::factory()->create(['user_id' => $order->user_id, 'order_id' => $order->id, 'subject' => 'Ada invoice']);
        CustomerReport::factory()->create(['subject' => 'Tanpa invoice']);

        $this->get('/admin/laporan?q=PRO20260917AAAAA')->assertInertia(fn ($page) => $page
            ->has('reports.data', 1)->where('reports.data.0.subject', 'Ada invoice')->where('reports.data.0.order_code', 'PRO20260917AAAAA'));
        $this->get('/admin/laporan?q=Tanpa')->assertInertia(fn ($page) => $page->where('reports.data.0.order_code', null)->where('reports.data.0.order', null));

        // Detail laporan memakai data pesanan yang sama dengan modal Detail Pesanan.
        $this->get('/admin/laporan?q=Ada')->assertInertia(fn ($page) => $page
            ->where('reports.data.0.order.code', 'PRO20260917AAAAA')
            ->where('reports.data.0.order.total', $order->total)
            ->has('reports.data.0.order.deliveries')
            ->has('reports.data.0.order.claims')
            ->has('reports.data.0.order.service_ends_on'));
    }

    public function test_each_report_lists_other_reports_from_the_same_customer_including_same_day_duplicates(): void
    {
        $user = User::factory()->create();
        $pagi = CustomerReport::factory()->create(['user_id' => $user->id, 'created_at' => now()->subHours(8)]);
        $malam = CustomerReport::factory()->create(['user_id' => $user->id, 'created_at' => now()->subHour()]);
        CustomerReport::factory()->create(); // pelanggan lain, paling baru

        // Laporan ganda tetap masuk, tidak ditolak atau digabung.
        $this->assertSame(2, CustomerReport::where('user_id', $user->id)->count());

        $this->get('/admin/laporan')->assertInertia(fn ($page) => $page
            ->where('reports.data.0.id', $pagi->id)
            ->where('reports.data.0.others.0.id', $malam->id)
            ->where('reports.data.1.others.0.id', $pagi->id)
            ->where('reports.data.2.others', []));
    }

    // ---------- resolve ----------

    public function test_resolve_marks_the_report_done_with_resolution_note_and_admin(): void
    {
        $report = CustomerReport::factory()->create();

        $this->patch("/admin/laporan/{$report->id}/selesai", ['resolution' => 'not_account_issue', 'resolution_note' => 'Salah password sendiri'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $report->refresh();
        $this->assertSame('resolved', $report->status);
        $this->assertSame(ReportResolution::NotAccountIssue, $report->resolution);
        $this->assertSame('Salah password sendiri', $report->resolution_note);
        $this->assertSame($this->admin->id, $report->resolved_by);
        $this->assertNotNull($report->resolved_at);
    }

    public function test_duplicate_resolution_points_to_the_original_report_of_the_same_customer(): void
    {
        $user = User::factory()->create();
        $pagi = CustomerReport::factory()->create(['user_id' => $user->id]);
        $malam = CustomerReport::factory()->create(['user_id' => $user->id]);
        $orang = CustomerReport::factory()->create();

        $this->patch("/admin/laporan/{$malam->id}/selesai", ['resolution' => 'duplicate', 'duplicate_of_id' => $pagi->id])->assertSessionHasNoErrors();
        $this->assertSame($pagi->id, $malam->fresh()->duplicate_of_id);

        // Bukan milik pelanggan yang sama, dan tidak boleh menunjuk dirinya sendiri.
        $this->patch("/admin/laporan/{$pagi->id}/selesai", ['resolution' => 'duplicate', 'duplicate_of_id' => $orang->id])->assertSessionHasErrors('duplicate_of_id');
        $this->patch("/admin/laporan/{$pagi->id}/selesai", ['resolution' => 'duplicate', 'duplicate_of_id' => $pagi->id])->assertSessionHasErrors('duplicate_of_id');
        $this->assertTrue($pagi->fresh()->isOpen());
    }

    public function test_duplicate_reference_is_ignored_for_other_resolutions(): void
    {
        $user = User::factory()->create();
        $a = CustomerReport::factory()->create(['user_id' => $user->id]);
        $b = CustomerReport::factory()->create(['user_id' => $user->id]);

        $this->patch("/admin/laporan/{$b->id}/selesai", ['resolution' => 'rejected', 'duplicate_of_id' => $a->id])->assertSessionHasNoErrors();

        $this->assertNull($b->fresh()->duplicate_of_id);
    }

    public function test_resolve_validation_and_system_only_resolution_is_not_selectable(): void
    {
        $report = CustomerReport::factory()->create();
        $url = "/admin/laporan/{$report->id}/selesai";

        $this->patch($url, [])->assertSessionHasErrors('resolution');
        $this->patch($url, ['resolution' => 'ngawur'])->assertSessionHasErrors('resolution');
        $this->patch($url, ['resolution' => 'account_replaced'])->assertSessionHasErrors('resolution');
        $this->patch($url, ['resolution' => 'other', 'resolution_note' => str_repeat('a', 501)])->assertSessionHasErrors('resolution_note');
        $this->assertTrue($report->fresh()->isOpen());
    }

    public function test_resolving_an_already_resolved_report_is_refused_and_keeps_the_first_resolution(): void
    {
        $report = CustomerReport::factory()->create();
        $this->patch("/admin/laporan/{$report->id}/selesai", ['resolution' => 'rejected']);

        $this->patch("/admin/laporan/{$report->id}/selesai", ['resolution' => 'other'])->assertSessionHas('error');

        $this->assertSame(ReportResolution::Rejected, $report->fresh()->resolution);
    }

    public function test_reopen_clears_the_resolution(): void
    {
        $report = CustomerReport::factory()->create();
        $this->patch("/admin/laporan/{$report->id}/selesai", ['resolution' => 'other', 'resolution_note' => 'x']);

        $this->patch("/admin/laporan/{$report->id}/buka")->assertSessionHas('success');

        $report->refresh();
        $this->assertTrue($report->isOpen());
        $this->assertNull($report->resolution);
        $this->assertNull($report->resolution_note);
        $this->assertNull($report->resolved_at);

        $this->patch("/admin/laporan/{$report->id}/buka")->assertSessionHas('error');
    }

    public function test_bulk_resolve_only_touches_open_reports_and_does_not_allow_duplicate(): void
    {
        [$a, $b] = CustomerReport::factory()->count(2)->create()->all();
        $done = CustomerReport::factory()->resolved()->create(['resolution' => 'rejected']);

        $this->patch('/admin/laporan/massal/selesai', ['ids' => [$a->id, $b->id, $done->id], 'resolution' => 'duplicate'])->assertSessionHasErrors('resolution');
        $this->patch('/admin/laporan/massal/selesai', ['ids' => [$a->id, $b->id, $done->id], 'resolution' => 'other'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', '2 laporan diselesaikan.');

        $this->assertSame(['resolved', 'resolved'], [$a->fresh()->status, $b->fresh()->status]);
        $this->assertSame(ReportResolution::Rejected, $done->fresh()->resolution); // tidak ditimpa
        $this->patch('/admin/laporan/massal/selesai', ['ids' => [9999], 'resolution' => 'other'])->assertSessionHasErrors('ids.0');
    }

    // ---------- hapus ----------

    public function test_only_resolved_reports_can_be_deleted_and_attachments_are_removed(): void
    {
        Storage::fake('local');
        $open = CustomerReport::factory()->create();
        $done = CustomerReport::factory()->resolved()->create();
        Storage::disk('local')->put('reports/bukti.png', 'x');
        ReportAttachment::create(['report_id' => $done->id, 'path' => 'reports/bukti.png', 'name' => 'bukti.png', 'size' => 1]);

        $this->delete("/admin/laporan/{$open->id}")->assertSessionHas('error');
        $this->assertNotNull($open->fresh());

        $this->delete("/admin/laporan/{$done->id}")->assertSessionHas('success');
        $this->assertNull($done->fresh());
        $this->assertDatabaseCount('report_attachments', 0);
        Storage::disk('local')->assertMissing('reports/bukti.png');
    }

    public function test_attachments_are_served_privately_to_admins_only(): void
    {
        Storage::fake('local');
        $report = CustomerReport::factory()->create();
        Storage::disk('local')->put('reports/1/bukti.png', 'isi-gambar');
        $attachment = ReportAttachment::create(['report_id' => $report->id, 'path' => 'reports/1/bukti.png', 'name' => 'bukti.png', 'size' => 10]);

        $this->assertSame(route('admin.reports.attachment', $attachment), $attachment->url);
        $this->get($attachment->url)->assertOk();

        $this->actingAs(\App\Models\User::factory()->create())->get($attachment->url)->assertForbidden();
        auth()->logout();
        $this->get($attachment->url)->assertRedirect(route('login'));
    }

    public function test_bulk_delete_skips_open_reports(): void
    {
        $open = CustomerReport::factory()->create();
        [$a, $b] = CustomerReport::factory()->resolved()->count(2)->create()->all();

        $this->delete('/admin/laporan/massal', ['ids' => [$open->id, $a->id, $b->id]])
            ->assertSessionHas('success', '2 laporan dihapus. 1 laporan yang belum selesai dilewati.');

        $this->assertDatabaseCount('customer_reports', 1);
        $this->assertNotNull($open->fresh());
    }

    // ---------- pengaturan notifikasi ----------

    public function test_notification_settings_default_to_off_with_no_address(): void
    {
        $this->get('/admin/pengaturan')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->where('notifications.email_enabled', false)
            ->where('notifications.email_to', ''));
    }

    public function test_notification_settings_validation_and_saving(): void
    {
        $this->put('/admin/pengaturan/notifikasi', ['email_enabled' => true, 'email_to' => ''])->assertSessionHasErrors('email_to');
        $this->put('/admin/pengaturan/notifikasi', ['email_enabled' => true, 'email_to' => 'bukan-email'])->assertSessionHasErrors('email_to');
        $this->assertFalse(Setting::bool(Setting::NOTIFY_EMAIL_ENABLED));

        $this->put('/admin/pengaturan/notifikasi', ['email_enabled' => true, 'email_to' => 'owner@uji.test'])->assertSessionHasNoErrors();
        $this->assertTrue(Setting::bool(Setting::NOTIFY_EMAIL_ENABLED));
        $this->assertSame('owner@uji.test', Setting::get(Setting::NOTIFY_EMAIL_TO));

        // Mematikan tidak butuh alamat.
        $this->put('/admin/pengaturan/notifikasi', ['email_enabled' => false, 'email_to' => ''])->assertSessionHasNoErrors();
        $this->assertFalse(Setting::bool(Setting::NOTIFY_EMAIL_ENABLED));
    }

    // ---------- email laporan baru ----------

    public function test_no_email_is_sent_for_new_reports_by_default(): void
    {
        Mail::fake();

        CustomerReport::factory()->create();

        Mail::assertNothingOutgoing();
        Mail::assertNothingQueued();
    }

    public function test_email_is_sent_to_the_configured_address_when_enabled(): void
    {
        $this->withoutDefer();
        Mail::fake();
        Setting::put(Setting::NOTIFY_EMAIL_ENABLED, true);
        Setting::put(Setting::NOTIFY_EMAIL_TO, 'owner@uji.test');

        $report = CustomerReport::factory()->create(['subject' => 'Akun mati']);

        Mail::assertQueued(NewReportMail::class, fn (NewReportMail $mail) => $mail->hasTo('owner@uji.test') && $mail->report->is($report));
        $this->assertStringContainsString('Akun mati', (new NewReportMail($report->load('user')))->envelope()->subject);
    }

    public function test_no_email_when_enabled_but_address_is_missing_or_invalid_or_switch_is_off(): void
    {
        $this->withoutDefer();
        Mail::fake();

        Setting::put(Setting::NOTIFY_EMAIL_ENABLED, true);
        CustomerReport::factory()->create();

        Setting::put(Setting::NOTIFY_EMAIL_TO, 'rusak');
        CustomerReport::factory()->create();

        Setting::put(Setting::NOTIFY_EMAIL_ENABLED, false);
        Setting::put(Setting::NOTIFY_EMAIL_TO, 'owner@uji.test');
        CustomerReport::factory()->create();

        Mail::assertNothingOutgoing();
    }

    public function test_mail_failure_does_not_break_report_creation(): void
    {
        $this->withoutDefer();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        Setting::put(Setting::NOTIFY_EMAIL_ENABLED, true);
        Setting::put(Setting::NOTIFY_EMAIL_TO, 'owner@uji.test');

        $report = CustomerReport::factory()->create();

        $this->assertNotNull($report->fresh());
    }
}
