<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BackupAdminTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups-'.uniqid());
        $this->app->instance(DatabaseBackup::class, new DatabaseBackup($this->dir));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function backup(): DatabaseBackup
    {
        return app(DatabaseBackup::class);
    }

    private function sql(string $name): string
    {
        return gzdecode(File::get($this->dir.'/'.$name));
    }

    public function test_only_admins_can_reach_backup_routes(): void
    {
        $routes = [['get', '/admin/backup'], ['post', '/admin/backup'], ['put', '/admin/backup/pengaturan'],
            ['get', '/admin/backup/backup-manual-2026-01-01-000000.sql.gz/unduh'], ['delete', '/admin/backup/backup-manual-2026-01-01-000000.sql.gz']];

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertDirectoryDoesNotExist($this->dir);
    }

    public function test_manual_backup_contains_structure_and_data_but_not_sessions_and_is_recorded(): void
    {
        Product::factory()->create(['name' => "Netflix O'Premium"]);

        $this->post('/admin/backup')->assertSessionHas('success');

        $file = $this->backup()->all()[0];
        $this->assertSame('manual', $file['type']);
        $this->assertTrue($this->backup()->isComplete($file['name']));
        $sql = $this->sql($file['name']);
        $this->assertStringContainsString('CREATE TABLE "products"', $sql);
        $this->assertStringContainsString("Netflix O''Premium", $sql);
        $this->assertStringContainsString('CREATE TABLE "sessions"', $sql);
        $this->assertStringNotContainsString('INSERT INTO "sessions"', $sql);
        $this->assertStringNotContainsString('INSERT INTO "password_reset_tokens"', $sql);

        $log = ActivityLog::where('action', 'admin.backups.store')->sole();
        $this->assertStringContainsString($file['name'], $log->description);
    }

    public function test_backup_can_be_restored_into_an_empty_database(): void
    {
        Product::factory()->count(3)->create();
        $name = $this->backup()->create()['name'];

        // Pulihkan ke database SQLite baru lalu bandingkan isinya.
        $restored = new \PDO('sqlite::memory:');
        $restored->exec($this->sql($name));
        $this->assertSame(3, (int) $restored->query('select count(*) from products')->fetchColumn());
        $this->assertSame(Product::orderBy('id')->pluck('name')->all(), $restored->query('select name from products order by id')->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function test_index_download_and_delete(): void
    {
        $name = $this->backup()->create()['name'];

        $this->get('/admin/backup')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Backups/Index')
            ->where('backups.0.name', $name)->where('settings.enabled', true)->where('settings.interval_days', 1)->where('settings.keep', 7));
        $this->get("/admin/backup/{$name}/unduh")->assertOk()->assertDownload($name);
        $this->get('/admin/backup/..%2F..%2F.env/unduh')->assertNotFound();

        $this->delete("/admin/backup/{$name}")->assertSessionHas('success');
        $this->assertSame([], $this->backup()->all());
        $this->delete("/admin/backup/{$name}")->assertNotFound();
    }

    public function test_settings_validate_and_prune_old_backups_beyond_the_keep_count(): void
    {
        foreach (['2026-01-01-000000', '2026-01-02-000000', '2026-01-03-000000'] as $stamp) {
            File::ensureDirectoryExists($this->dir);
            File::put("{$this->dir}/backup-otomatis-{$stamp}.sql.gz", gzencode('x'));
        }

        $this->put('/admin/backup/pengaturan', ['enabled' => true, 'interval_days' => 0, 'keep' => 999])->assertSessionHasErrors(['interval_days', 'keep']);
        $this->put('/admin/backup/pengaturan', ['enabled' => false, 'interval_days' => 3, 'keep' => 2])->assertSessionHas('success');

        $this->assertFalse(Setting::backupEnabled());
        $this->assertSame(3, Setting::backupIntervalDays());
        $this->assertSame(['backup-otomatis-2026-01-03-000000.sql.gz', 'backup-otomatis-2026-01-02-000000.sql.gz'], array_column($this->backup()->all(), 'name'));
    }

    public function test_automatic_backup_follows_the_switch_and_interval(): void
    {
        Setting::put(Setting::BACKUP_INTERVAL_DAYS, '3');

        Setting::put(Setting::BACKUP_ENABLED, false);
        $this->artisan('backup:database --auto')->assertSuccessful();
        $this->assertSame([], $this->backup()->all());

        Setting::put(Setting::BACKUP_ENABLED, true);
        $this->artisan('backup:database --auto')->assertSuccessful();
        $this->assertCount(1, $this->backup()->all());
        $this->assertSame('otomatis', $this->backup()->all()[0]['type']);
        $this->assertStringContainsString('Membuat backup database terjadwal: backup-otomatis-', ActivityLog::where('action', 'backup:database')->sole()->description);

        // Belum 3 hari: tidak membuat lagi. Setelah 3 hari: dibuat.
        $this->travel(2)->days();
        $this->artisan('backup:database --auto')->assertSuccessful();
        $this->assertCount(1, $this->backup()->all());
        $this->travel(1)->days();
        $this->artisan('backup:database --auto')->assertSuccessful();
        $this->assertCount(2, $this->backup()->all());
    }
}
