<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Utama']);
    }

    // ---------- pencatatan ----------

    public function test_admin_action_is_recorded_with_subject_and_before_after_values(): void
    {
        $product = Product::factory()->create(['name' => 'Netflix Premium', 'is_active' => true]);

        $this->actingAs($this->admin)->patch("/admin/produk/{$product->id}/aktif")->assertSessionHas('success');

        $log = ActivityLog::sole();
        $this->assertSame('admin', $log->actor_type);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame('Admin Utama', $log->actor_name);
        $this->assertSame('admin.products.toggle', $log->action);
        $this->assertSame('products', $log->module);
        $this->assertSame('Mengubah status aktif produk Netflix Premium', $log->description);
        $this->assertSame(['product', $product->id, 'Netflix Premium'], [$log->subject_type, $log->subject_id, $log->subject_label]);
        $this->assertSame('updated', $log->changes[0]['event']);
        $this->assertEquals([1, 0], array_map('intval', $log->changes[0]['fields']['is_active']));
        $this->assertArrayNotHasKey('updated_at', $log->changes[0]['fields']);
    }

    public function test_customer_action_uses_the_created_record_as_subject(): void
    {
        $user = User::factory()->create(['name' => 'Sari']);

        $this->actingAs($user)->post('/laporan', ['category' => 'account', 'subject' => 'Akun tidak bisa login', 'body' => 'Sejak tadi pagi akun saya tidak bisa dipakai login.'])
            ->assertSessionHasNoErrors();

        $log = ActivityLog::where('action', 'reports.store')->sole();
        $this->assertSame(['user', 'Sari', 'reports'], [$log->actor_type, $log->actor_name, $log->module]);
        $this->assertSame('Mengirim laporan masalah Akun tidak bisa login', $log->description);
        $this->assertSame('customer_report', $log->subject_type);
        $this->assertSame('created', $log->changes[0]['event']);
        $this->assertSame('127.0.0.1', $log->ip);
    }

    public function test_settings_changes_have_a_clean_description_and_no_text_subject_id(): void
    {
        $this->actingAs($this->admin)->put('/admin/pengaturan/log', ['system_days' => 20, 'activity_days' => 100])->assertSessionHas('success');

        $log = ActivityLog::sole();
        $this->assertSame('Mengubah masa simpan log', $log->description);
        $this->assertNull($log->subject_id);
        $this->assertSame('logs.system.days', $log->changes[0]['label']);
    }

    public function test_failed_actions_and_unlisted_routes_are_not_recorded(): void
    {
        $this->actingAs($this->admin);

        // Galat validasi.
        $this->post('/admin/user', ['name' => ''])->assertSessionHasErrors('name');
        // Halaman biasa (GET) dan rute yang tidak terdaftar (cek voucher, chat).
        $this->get('/admin/produk')->assertOk();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_guests_redirected_from_admin_routes_are_not_recorded(): void
    {
        $product = Product::factory()->create();

        $this->patch("/admin/produk/{$product->id}/aktif")->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch("/admin/produk/{$product->id}/aktif")->assertForbidden();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_secret_columns_are_never_stored(): void
    {
        $user = User::factory()->create(['password' => 'SandiLama123']);

        $this->actingAs($user)->patch('/profil/sandi', [
            'current_password' => 'SandiLama123', 'password' => 'SandiBaru456', 'password_confirmation' => 'SandiBaru456',
        ])->assertSessionHasNoErrors();

        $log = ActivityLog::where('action', 'profil.password')->sole();
        $this->assertSame('user', $log->actor_type);
        $this->assertSame(['(rahasia)', '(diubah)'], $log->changes[0]['fields']['password']);
        $raw = json_encode($log->getAttributes());
        $this->assertStringNotContainsString('SandiBaru456', $raw);
        $this->assertStringNotContainsString($user->fresh()->password, $raw);
    }

    public function test_deleting_own_account_keeps_the_original_name_of_the_actor(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@uji.test', 'password' => 'Rahasia123']);

        $this->actingAs($user)->delete('/profil', ['confirm' => 'HAPUS', 'password' => 'Rahasia123']);

        $log = ActivityLog::where('action', 'profil.destroy')->sole();
        $this->assertSame('Budi Santoso', $log->actor_name);
        $this->assertSame('budi@uji.test', $log->actor_email);
    }

    public function test_login_failed_login_and_logout_are_recorded_without_the_password(): void
    {
        $user = User::factory()->create(['email' => 'sari@uji.test', 'password' => 'Benar12345']);

        $this->post('/login', ['email' => 'sari@uji.test', 'password' => 'Salah12345']);
        $this->post('/login', ['email' => 'tidakada@uji.test', 'password' => 'Salah12345']);
        $this->post('/login', ['email' => 'sari@uji.test', 'password' => 'Benar12345']);
        $this->post('/logout');

        $logs = ActivityLog::orderBy('id')->get(['action', 'actor_type', 'actor_email', 'description', 'subject_id']);
        $this->assertSame([
            ['auth.failed', 'guest', 'sari@uji.test', 'Gagal masuk: sandi salah', $user->id],
            ['auth.failed', 'guest', 'tidakada@uji.test', 'Gagal masuk: email tidak terdaftar', null],
            ['auth.login', 'user', 'sari@uji.test', 'Masuk', $user->id],
            ['auth.logout', 'user', 'sari@uji.test', 'Keluar', $user->id],
        ], $logs->map(fn ($l) => [$l->action, $l->actor_type, $l->actor_email, $l->description, $l->subject_id])->all());
        $this->assertStringNotContainsString('Salah12345', ActivityLog::all()->toJson());
    }

    public function test_scheduled_command_is_recorded_only_when_it_changes_data(): void
    {
        $this->artisan('accounts:expire')->assertSuccessful();
        $this->assertSame(0, ActivityLog::count());

        Account::factory()->count(2)->create(['started_at' => now()->subDays(40)->toDateString(), 'active_days' => 28]);
        $this->artisan('accounts:expire')->assertSuccessful();

        $log = ActivityLog::sole();
        $this->assertSame('system', $log->actor_type);
        $this->assertNull($log->actor_id);
        $this->assertSame('Menandai stok akun yang habis masa aktifnya: 2 akun ditandai kedaluwarsa', $log->description);
        $this->assertSame(2, Account::where('status', AccountStatus::Expired)->count());
    }

    public function test_prune_removes_activity_older_than_the_retention_and_records_itself(): void
    {
        File::ensureDirectoryExists($dir = storage_path('framework/testing/logs-'.uniqid()));
        $this->app->instance(SystemLog::class, new SystemLog($dir));
        Setting::put(Setting::LOG_ACTIVITY_DAYS, '30');
        $old = ActivityLog::create(['actor_type' => 'system', 'action' => 'x', 'module' => 'system', 'description' => 'Lama', 'created_at' => now()->subDays(31)]);
        $recent = ActivityLog::create(['actor_type' => 'system', 'action' => 'x', 'module' => 'system', 'description' => 'Baru', 'created_at' => now()->subDays(29)]);

        $this->artisan('logs:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertStringContainsString('1 catatan aktivitas (lebih dari 30 hari)', ActivityLog::where('action', 'logs:prune')->sole()->description);
        File::deleteDirectory($dir);
    }

    // ---------- halaman admin ----------

    public function test_changes_are_shown_with_readable_labels_and_values(): void
    {
        $product = Product::factory()->create(['name' => 'Netflix Premium']);
        ActivityLog::create(['actor_type' => 'admin', 'action' => 'x', 'module' => 'orders', 'description' => 'Uji', 'changes' => [
            ['model' => 'Pesanan', 'type' => 'order', 'id' => 1, 'label' => 'PRO1', 'event' => 'updated', 'fields' => [
                'status' => ['verifying', 'paid'],
                'total' => [45000, 50000],
                'product_id' => [999, $product->id],
                'paid_at' => [null, '2026-10-11 09:05:00'],
                'admin_note' => ['', 'Cek ulang'],
            ]],
            ['model' => 'Produk', 'type' => 'product', 'id' => $product->id, 'label' => 'Netflix Premium', 'event' => 'updated', 'fields' => [
                'is_active' => [1, 0],
            ]],
            ['model' => 'User', 'type' => 'user', 'id' => 1, 'label' => 'a@b.test', 'event' => 'updated', 'fields' => [
                'password' => ['(rahasia)', '(diubah)'],
                'remember_token' => ['(rahasia)', '(diubah)'],
            ]],
            ['model' => 'Pengaturan', 'type' => 'setting', 'id' => 'backup.auto.enabled', 'label' => 'backup.auto.enabled', 'event' => 'updated', 'fields' => ['value' => ['1', '0']]],
            ['model' => 'Pengaturan', 'type' => 'setting', 'id' => 'store.hero.title', 'label' => 'store.hero.title', 'event' => 'updated', 'fields' => ['value' => ['Lama', 'Baru']]],
            ['more' => 4],
        ]]);

        $this->actingAs($this->admin)->get('/admin/log-aktivitas')->assertInertia(fn (Assert $p) => $p
            ->where('logs.data.0.changes.0.rows', [
                ['Status', 'Menunggu verifikasi', 'Berjalan'],
                ['Total', 'Rp 45.000', 'Rp 50.000'],
                ['Produk', '#999', 'Netflix Premium'],
                ['Lunas pada', null, '11 Okt 2026 09.05'],
                ['Catatan admin', null, 'Cek ulang'],
            ])
            ->where('logs.data.0.changes.1.rows', [['Aktif', 'Ya', 'Tidak']])
            ->where('logs.data.0.changes.2.rows', [['Sandi', '(rahasia)', '(diubah)']])
            ->where('logs.data.0.changes.3.label', 'Backup otomatis')
            ->where('logs.data.0.changes.3.rows', [['Nilai', 'Ya', 'Tidak']])
            ->where('logs.data.0.changes.4.label', 'Tampilan toko: hero.title')
            ->where('logs.data.0.changes.4.rows', [['Nilai', 'Lama', 'Baru']])
            ->where('logs.data.0.changes.5', ['more' => 4]));
    }

    public function test_only_admins_can_open_the_activity_page(): void
    {
        $this->get('/admin/log-aktivitas')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/log-aktivitas')->assertForbidden();
    }

    public function test_page_lists_newest_first_and_filters_by_actor_module_keyword_date_subject_and_user(): void
    {
        $budi = User::factory()->create(['name' => 'Budi']);
        $make = fn (array $a) => ActivityLog::create([...['actor_type' => 'admin', 'action' => 'x', 'module' => 'orders', 'description' => 'Aksi'], ...$a]);
        $make(['description' => 'Mengonfirmasi pembayaran pesanan INV-1', 'subject_type' => 'order', 'subject_id' => 1, 'created_at' => now()->subDays(3)]);
        $make(['actor_type' => 'user', 'actor_id' => $budi->id, 'actor_name' => 'Budi', 'description' => 'Membuat pesanan INV-2', 'subject_type' => 'order', 'subject_id' => 2, 'created_at' => now()->subDay()]);
        $make(['actor_type' => 'system', 'module' => 'system', 'description' => 'Mengedaluwarsakan pesanan', 'created_at' => now()]);
        $make(['module' => 'users', 'description' => 'Mengubah user Budi', 'subject_type' => 'user', 'subject_id' => $budi->id, 'created_at' => now()]);

        $this->actingAs($this->admin);
        $page = fn (string $query = '') => $this->get('/admin/log-aktivitas'.$query)->assertOk();

        $page()->assertInertia(fn (Assert $p) => $p->component('Logs/Activity')
            ->where('logs.total', 4)->where('logs.data.0.description', 'Mengubah user Budi')
            ->where('counts', ['all' => 4, 'admin' => 2, 'user' => 1, 'guest' => 0, 'system' => 1])
            ->where('retentionDays', 90));
        $page('?actor=system')->assertInertia(fn (Assert $p) => $p->where('logs.total', 1)->where('counts.all', 4));
        $page('?module=orders')->assertInertia(fn (Assert $p) => $p->where('logs.total', 2));
        $page('?q=INV-2')->assertInertia(fn (Assert $p) => $p->where('logs.total', 1)->where('logs.data.0.actor_name', 'Budi'));
        $page('?from='.now()->subDays(2)->toDateString())->assertInertia(fn (Assert $p) => $p->where('logs.total', 3));
        $page('?to='.now()->subDays(2)->toDateString())->assertInertia(fn (Assert $p) => $p->where('logs.total', 1));
        $page('?subjek=order:1')->assertInertia(fn (Assert $p) => $p->where('logs.total', 1)->where('filters.subject', 'order:1'));
        // Riwayat user: yang dia lakukan dan yang dilakukan terhadap dia.
        $page('?user='.$budi->id)->assertInertia(fn (Assert $p) => $p->where('logs.total', 2));
        // Nilai filter tidak sah diabaikan.
        $page('?actor=hacker&module=x&from=kemarin&subjek=1;drop')->assertInertia(fn (Assert $p) => $p->where('logs.total', 4)
            ->where('filters.actor', 'all')->where('filters.from', null)->where('filters.subject', null));
    }
}
