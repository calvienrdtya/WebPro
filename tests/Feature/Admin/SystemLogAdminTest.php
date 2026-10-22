<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemLogAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/logs-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        $this->app->instance(SystemLog::class, new SystemLog($this->dir));
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(12, 0));

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** Isi log dengan format asli Laravel: tanpa konteks, dengan konteks + extra, dan exception multi baris. */
    private function writeLog(string $name, array $entries): void
    {
        File::put($this->dir.'/'.$name, implode('', $entries));
    }

    private function line(string $time, string $level, string $text): string
    {
        return "[2026-10-10 {$time}] production.{$level}: {$text}\n";
    }

    private function exception(string $time, string $message, string $extra = ''): string
    {
        return "[2026-10-10 {$time}] production.ERROR: {$message} {\"userId\":7,\"exception\":\"[object] (Illuminate\\\\Database\\\\QueryException(code: 2002): {$message} at /app/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838)\n"
            ."[stacktrace]\n#0 /app/vendor/laravel/framework/src/Illuminate/Database/Connection.php(794): Illuminate\\\\Database\\\\Connection->runQueryCallback()\n#1 {main}\n"
            ."\"} {$extra}\n";
    }

    private function seedLogs(): void
    {
        $extra = '{"url":"https://toko.test/checkout?token=abc123secret","method":"POST","ip":"10.0.0.1","user_id":7}';
        $this->writeLog('laravel-2026-10-10.log', [
            $this->line('08:00:00', 'INFO', 'Cron selesai'),
            $this->line('09:00:00', 'WARNING', 'Stok menipis {"product":"Netflix"} '.$extra),
            $this->exception('10:00:00', 'Connection refused', $extra),
            $this->line('11:00:00', 'ERROR', 'Login gagal {"email":"a@b.test","password":"rahasia-sekali","hash":"$2y$12$abcdefghijklmnopqrstuuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0"}'),
        ]);
        $this->writeLog('laravel-2026-10-09.log', [$this->line('23:00:00', 'ERROR', 'Kemarin')]);
        $this->writeLog('laravel.log', [$this->line('01:00:00', 'DEBUG', 'File lama')]);
    }

    public function test_guests_and_regular_users_cannot_reach_log_routes(): void
    {
        $this->seedLogs();
        $routes = [
            ['get', '/admin/log-sistem'], ['get', '/admin/log-sistem/laravel.log/entri/0'], ['get', '/admin/log-sistem/laravel.log/unduh'],
            ['delete', '/admin/log-sistem/laravel.log'], ['put', '/admin/pengaturan/log'],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertFileExists($this->dir.'/laravel.log');
    }

    public function test_index_lists_files_newest_first_and_entries_newest_first_with_level_counts(): void
    {
        $this->seedLogs();

        $this->get('/admin/log-sistem')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Logs/System')
            ->where('files.0.name', 'laravel-2026-10-10.log')
            ->where('files.1.name', 'laravel-2026-10-09.log')
            ->where('files.2.name', 'laravel.log')
            ->where('files.2.date', null)
            ->where('file', 'laravel-2026-10-10.log')
            ->where('total', 4)
            ->where('counts', ['error' => 2, 'warning' => 1, 'info' => 1, 'debug' => 0])
            ->where('entries.0.message', 'Login gagal')
            ->where('entries.1.message', 'Connection refused')
            ->where('entries.1.exception', 'QueryException')
            ->where('entries.1.location', '/app/vendor/laravel/framework/src/Illuminate/Database/Connection.php:838')
            ->where('entries.1.url', '/checkout')
            ->where('entries.1.method', 'POST')
            ->where('entries.1.user_id', 7)
            ->where('entries.2.message', 'Stok menipis')
            ->where('entries.3.message', 'Cron selesai')
            ->where('retentionDays', 14));
    }

    public function test_filters_by_level_and_keyword_paginates_and_opens_other_files(): void
    {
        $this->seedLogs();

        $this->get('/admin/log-sistem?level=error')->assertInertia(fn (Assert $page) => $page
            ->where('total', 2)->where('filters.level', 'error')->has('entries', 2));
        $this->get('/admin/log-sistem?q=netflix')->assertInertia(fn (Assert $page) => $page
            ->where('total', 1)->where('entries.0.message', 'Stok menipis')->where('counts.warning', 1)->where('counts.error', 0));
        $this->get('/admin/log-sistem?file=laravel.log')->assertInertia(fn (Assert $page) => $page
            ->where('file', 'laravel.log')->where('entries.0.message', 'File lama'));
        // Nama file tidak dikenal kembali ke file terbaru.
        $this->get('/admin/log-sistem?file=../../.env')->assertInertia(fn (Assert $page) => $page->where('file', 'laravel-2026-10-10.log'));

        config(['proakses.logs.per_page' => 3]);
        $this->get('/admin/log-sistem?page=2')->assertInertia(fn (Assert $page) => $page
            ->where('total', 4)->has('entries', 1)->where('entries.0.message', 'Cron selesai'));
    }

    public function test_detail_shows_exception_request_and_trace_with_secrets_masked(): void
    {
        $this->seedLogs();
        $entries = app(SystemLog::class)->read('laravel-2026-10-10.log', null, null, 1, 50)['entries'];

        $ex = $this->getJson('/admin/log-sistem/laravel-2026-10-10.log/entri/'.$entries[1]['offset'])->assertOk();
        $ex->assertJsonPath('exception.class', 'Illuminate\\Database\\QueryException')
            ->assertJsonPath('exception.line', 838)
            ->assertJsonPath('request.method', 'POST')
            ->assertJsonPath('request.ip', '10.0.0.1')
            ->assertJsonPath('request.user_id', 7)
            ->assertJsonPath('request.url', 'https://toko.test/checkout?token=[disamarkan]');
        $this->assertStringContainsString('Illuminate\\Database\\Connection->runQueryCallback()', $ex->json('trace'));
        $this->assertStringNotContainsString('abc123secret', $ex->getContent());

        $login = $this->getJson('/admin/log-sistem/laravel-2026-10-10.log/entri/'.$entries[0]['offset'])->assertOk();
        $this->assertStringNotContainsString('rahasia-sekali', $login->getContent());
        $this->assertStringNotContainsString('$2y$12$abcdef', $login->getContent());
        $this->assertStringContainsString('a@b.test', $login->json('context'));

        // Nilai rahasia dari config (mis. APP_KEY) tidak pernah tampil, di mana pun posisinya.
        $this->writeLog('laravel-2026-10-08.log', [$this->line('01:00:00', 'ERROR', 'Kunci '.config('app.key').' salah')]);
        $leak = $this->getJson('/admin/log-sistem/laravel-2026-10-08.log/entri/0')->assertOk();
        $this->assertStringNotContainsString(config('app.key'), $leak->getContent());
    }

    public function test_detail_rejects_offsets_that_are_not_an_entry_start_and_unknown_files(): void
    {
        $this->seedLogs();

        $this->getJson('/admin/log-sistem/laravel-2026-10-10.log/entri/5')->assertNotFound();
        $this->getJson('/admin/log-sistem/laravel-2026-10-10.log/entri/999999')->assertNotFound();
        $this->getJson('/admin/log-sistem/laravel-2026-01-01.log/entri/0')->assertNotFound();
        $this->get('/admin/log-sistem/.env/unduh')->assertNotFound();
        $this->get('/admin/log-sistem/..%2F.env/unduh')->assertNotFound();
    }

    public function test_download_and_delete_a_file(): void
    {
        $this->seedLogs();

        $this->get('/admin/log-sistem/laravel-2026-10-09.log/unduh')->assertOk()->assertDownload('laravel-2026-10-09.log');

        $this->delete('/admin/log-sistem/laravel-2026-10-09.log')->assertRedirect('/admin/log-sistem')->assertSessionHas('success');
        $this->assertFileDoesNotExist($this->dir.'/laravel-2026-10-09.log');
        $this->assertFileExists($this->dir.'/laravel-2026-10-10.log');
        $this->delete('/admin/log-sistem/laravel-2026-10-09.log')->assertNotFound();
    }

    public function test_large_files_only_read_the_tail(): void
    {
        $lines = [];
        for ($i = 0; $i < 400; $i++) {
            $lines[] = $this->line('08:00:00', 'INFO', "Baris nomor {$i} ".str_repeat('x', 40));
        }
        $this->writeLog('laravel-2026-10-10.log', $lines);
        config(['proakses.logs.max_read_mb' => 0.01]); // ~10 KB dari ~29 KB

        $this->get('/admin/log-sistem')->assertInertia(fn (Assert $page) => $page
            ->where('partial', true)
            ->where('entries.0.message', 'Baris nomor 399 '.str_repeat('x', 40))
            ->where('total', fn ($total) => $total > 100 && $total < 400));
    }

    public function test_sidebar_badge_counts_new_errors_until_the_menu_is_opened(): void
    {
        $this->seedLogs();
        Setting::put(Setting::LOG_SYSTEM_SEEN_AT, '2026-10-10 09:30:00');

        // Hanya error setelah terakhir dilihat (10:00 dan 11:00); error kemarin dan file lama tidak dihitung.
        $this->get('/admin/pengaturan')->assertInertia(fn (Assert $page) => $page->where('sidebarBadges.system_log', 2));

        $this->get('/admin/log-sistem');
        $this->get('/admin/pengaturan')->assertInertia(fn (Assert $page) => $page->where('sidebarBadges.system_log', 0));
    }

    public function test_prune_deletes_daily_files_older_than_the_retention_but_keeps_the_old_single_file(): void
    {
        $this->seedLogs();
        $this->writeLog('laravel-2026-09-20.log', [$this->line('01:00:00', 'ERROR', 'Lama')]);
        $this->writeLog('laravel-2026-10-02.log', [$this->line('01:00:00', 'ERROR', 'Agak lama')]);
        Setting::put(Setting::LOG_SYSTEM_DAYS, '7');

        $this->artisan('logs:prune')->assertSuccessful();

        $this->assertFileDoesNotExist($this->dir.'/laravel-2026-09-20.log');
        $this->assertFileDoesNotExist($this->dir.'/laravel-2026-10-02.log');
        $this->assertFileExists($this->dir.'/laravel-2026-10-09.log');
        $this->assertFileExists($this->dir.'/laravel-2026-10-10.log');
        $this->assertFileExists($this->dir.'/laravel.log');
    }

    public function test_retention_settings_are_validated_and_saved(): void
    {
        $this->get('/admin/pengaturan')->assertInertia(fn (Assert $page) => $page
            ->where('logs.system_days', 14)->where('logs.activity_days', 90));

        $this->put('/admin/pengaturan/log', ['system_days' => 0, 'activity_days' => 99999])->assertSessionHasErrors(['system_days', 'activity_days']);
        $this->put('/admin/pengaturan/log', ['system_days' => 30, 'activity_days' => 365])->assertSessionHas('success');

        $this->assertSame(30, Setting::systemLogDays());
        $this->assertSame(365, Setting::activityLogDays());
    }
}
