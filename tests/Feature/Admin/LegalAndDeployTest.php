<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\AiSettings;
use App\Support\LegalPages;
use App\Support\StoreProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegalAndDeployTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_legal_pages_show_the_default_draft_with_placeholders_filled(): void
    {
        $html = $this->get('/syarat-ketentuan')->assertOk()->getContent();
        $this->assertStringContainsString('<h1 class="text-[28px] font-bold tracking-tight text-[#0A0F1F] sm:text-4xl">Syarat &amp; Ketentuan</h1>', $html);
        $this->assertStringContainsString('Pembayaran hanya melalui QRIS', $html);
        $this->assertStringContainsString(StoreProfile::name().' menjual akses', $html);
        $this->assertStringNotContainsString('{nama_toko}', $html);
        $this->assertStringContainsString('Kebijakan Privasi', $html); // tautan "Lihat juga"

        $this->get('/kebijakan-privasi')->assertOk()->assertSee('Undang-Undang Nomor 27 Tahun 2022');
        // Masa simpan log di Kebijakan Privasi mengikuti Pengaturan.
        $this->get('/kebijakan-privasi')->assertSee('Catatan aktivitas disimpan paling lama 90 hari')->assertSee('paling lama 14 hari')->assertDontSee('{masa_log');
        Setting::put(Setting::LOG_ACTIVITY_DAYS, '180');
        $this->get('/kebijakan-privasi')->assertSee('Catatan aktivitas disimpan paling lama 180 hari');
        $this->get('/kebijakan-lain')->assertNotFound();
    }

    public function test_legal_pages_are_linked_from_footer_register_and_sitemap(): void
    {
        $this->get('/')->assertSee(route('legal.show', 'syarat-ketentuan'))->assertSee(route('legal.show', 'kebijakan-privasi'));
        $this->get('/register')->assertSee(route('legal.show', 'syarat-ketentuan'));
        $this->get('/sitemap.xml')->assertSee(route('legal.show', 'kebijakan-privasi'));
    }

    public function test_admin_can_update_legal_pages_safely(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/halaman-legal')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Legal/Index')->has('pages', 2)->where('pages.0.key', 'terms')->where('pages.0.is_default', true));

        $this->put('/admin/halaman-legal/terms', ['body' => "## Aturan baru\nBerlaku untuk {nama_toko}.\n<script>alert(1)</script>\n[klik](javascript:alert(1))"])->assertSessionHas('success');

        $html = $this->get('/syarat-ketentuan')->getContent();
        $this->assertStringContainsString('<h2>Aturan baru</h2>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('Terakhir diperbarui', $html);

        $this->put('/admin/halaman-legal/terms', ['body' => config('legal.pages.terms.body')]);
        $this->assertTrue(LegalPages::isDefault('terms'));
        $this->put('/admin/halaman-legal/terms', ['body' => ''])->assertSessionHasErrors('body');
        $this->put('/admin/halaman-legal/lain', ['body' => 'x'])->assertNotFound();
    }

    public function test_reasons_section_is_editable(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/tampilan')->assertInertia(fn ($page) => $page->has('reasons.items', 6)->has('reasonIcons', 12));

        $this->put('/admin/tampilan/keunggulan', [
            'enabled' => true, 'badge' => '', 'title' => 'Alasan Belanja di Sini', 'subtitle' => '',
            'items' => [['icon' => 'clock', 'title' => 'Cepat Diverifikasi', 'text' => 'Admin memeriksa pembayaran setiap hari.']],
        ])->assertSessionHas('success');

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('Alasan Belanja di Sini', $html);
        $this->assertStringContainsString('Cepat Diverifikasi', $html);
        $this->assertStringNotContainsString('sejak 2024', $html);
        $this->assertSame(1, substr_count($html, 'kw-card'));

        $this->put('/admin/tampilan/keunggulan', ['enabled' => true, 'title' => 'X', 'items' => [['icon' => '<svg onload=alert(1)>', 'title' => 'a', 'text' => 'b']]])
            ->assertSessionHasErrors('items.0.icon');

        $this->put('/admin/tampilan/keunggulan', ['enabled' => false, 'title' => 'X', 'items' => []])->assertSessionHas('success');
        $this->assertStringNotContainsString('id="keunggulan"', $this->get('/')->getContent());
    }

    public function test_deploy_guide_lists_checks_without_secrets(): void
    {
        $this->actingAs($this->admin());
        Cache::forget('schedule.heartbeat');

        $this->get('/admin/panduan-deploy')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Deploy/Index')
            ->where('checks', fn ($checks) => collect($checks)->firstWhere('label', 'Cron schedule:run berjalan')['status'] === 'fail'
                && collect($checks)->firstWhere('label', 'APP_DEBUG mati') !== null)
            ->where('guide', fn ($g) => str_contains($g, '<h2>8. Cron (wajib)</h2>')));

        Cache::forever('schedule.heartbeat', now()->toIso8601String());
        $this->get('/admin/panduan-deploy')->assertInertia(fn ($page) => $page
            ->where('checks', fn ($checks) => collect($checks)->firstWhere('label', 'Cron schedule:run berjalan')['status'] === 'ok'));

        config(['mail.mailers.smtp.password' => 'SANDI-RAHASIA-UJI-123', 'services.google.client_secret' => 'GOOGLE-RAHASIA-UJI-456']);
        AiSettings::putApiKey('sk-RAHASIA-UJI-789');
        $response = $this->get('/admin/panduan-deploy')->getContent();
        foreach ([(string) config('app.key'), 'SANDI-RAHASIA-UJI-123', 'GOOGLE-RAHASIA-UJI-456', 'sk-RAHASIA-UJI-789'] as $secret) {
            $this->assertStringNotContainsString($secret, $response);
        }

        $this->actingAs(User::factory()->create())->get('/admin/panduan-deploy')->assertForbidden();
    }
}
