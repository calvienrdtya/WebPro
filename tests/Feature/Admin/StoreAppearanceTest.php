<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\StoreProfile;
use App\Support\VersionedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreAppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        Storage::fake('local'); // file asli gambar (ImageOptimizer)
        Cache::flush();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function home(array $overrides = []): array
    {
        return [
            'hero_title' => 'Nikmati *Hiburan Premium*, Dengan Harga Lebih *Hemat*',
            'hero_subtitle' => 'Sub', 'hero_badge' => '', 'hero_alt' => 'Alt', 'campus_title' => 'Kampus',
            'stat_orders' => true, 'stat_rating' => true, 'campus_enabled' => true,
            ...$overrides,
        ];
    }

    public function test_only_admins_can_reach_the_routes(): void
    {
        $routes = [['get', '/admin/tampilan'], ['post', '/admin/tampilan/identitas'], ['post', '/admin/tampilan/beranda'], ['put', '/admin/tampilan/sosmed'], ['post', '/admin/tampilan/kampus'], ['delete', '/admin/tampilan/kampus/0']];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
    }

    public function test_defaults_come_from_config(): void
    {
        $this->get('/admin/tampilan')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Store/Appearance')
            ->where('texts.name', 'ProAkses')
            ->where('images.logo.url', VersionedUrl::asset(config('store.logo')))
            ->where('images.logo.custom', false)
            ->where('images.logo.info.can_optimize', true) // gambar bawaan belum dioptimalkan
            ->where('images.og_image.url', VersionedUrl::asset('assets/hero-section.png'))
            ->has('campuses', 10)
            ->where('socials.0', fn ($s) => $s['platform'] === 'tiktok' && $s['active'] === true)
            ->has('socials', 7)
            ->where('socials.2', fn ($s) => $s['platform'] === 'whatsapp' && $s['managed'] === true && $s['active'] === true));
    }

    public function test_identity_is_saved_and_shown_in_the_store(): void
    {
        $this->post('/admin/tampilan/identitas', [
            'name' => 'Toko Uji', 'tagline' => 'Tagline uji', 'email' => 'cs@uji.test', 'footer_note' => '',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 100),
            'favicon' => UploadedFile::fake()->image('fav.png', 64, 64),
        ])->assertSessionHas('success');

        $logo = StoreProfile::uploaded('logo');
        Storage::disk('uploads')->assertExists($logo);
        $this->assertNotNull(StoreProfile::uploaded('favicon'));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('alt="Toko Uji"', $html);
        $this->assertStringContainsString(Storage::disk('uploads')->url($logo), $html);
        $this->assertStringContainsString('Tagline uji', $html);
        $this->assertStringContainsString('mailto:cs@uji.test', $html);
        $this->assertStringNotContainsString('Dibuat untuk Gen Z Indonesia', $html);

        $this->post('/admin/tampilan/identitas', ['name' => 'Toko Uji', 'remove_logo' => true])->assertSessionHas('success');
        Storage::disk('uploads')->assertMissing($logo);
        // URL gambar diberi penanda versi (waktu ubah file) agar cache browser ikut berganti saat file diganti.
        $this->assertSame(asset(config('store.logo')).'?v='.filemtime(public_path(config('store.logo'))), StoreProfile::imageUrl('logo'));
    }

    public function test_svg_logos_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->post('/admin/tampilan/identitas', ['name' => 'X', 'logo' => $svg])->assertSessionHasErrors('logo');
        $this->post('/admin/tampilan/identitas', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_hero_texts_images_and_real_stats(): void
    {
        $this->post('/admin/tampilan/beranda', $this->home([
            'hero_title' => 'Akun *Murah*, Cepat',
            'hero_badge' => 'Dipercaya {pelanggan} pelanggan',
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 3000, 2000),
        ]))->assertSessionHas('success');

        $hero = StoreProfile::uploaded('hero_image');
        // Dikecilkan ke lebar hero dan disimpan WebP; file asli tetap ada (ImageOptimizer).
        $this->assertStringEndsWith('.webp', $hero);
        $this->assertSame(1120, getimagesizefromstring(Storage::disk('uploads')->get($hero))[0]);
        // Gambar bagikan jatuh ke hero, memakai varian JPG agar terbaca semua aplikasi pratinjau tautan.
        $this->assertMatchesRegularExpression('#/store/share/\w+\.jpg\?v=\d+$#', StoreProfile::imageUrl('og_image'));

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('hh-gold" style="--d:0.25s">Murah</span><span class="hh-word" style="--d:0.35s">,</span>', $html);
        $this->assertStringContainsString('Dipercaya 0 pelanggan', $html);
        // Belum ada pesanan atau ulasan: chip angka tidak tampil, tidak ada angka palsu.
        $this->assertStringNotContainsString('pesanan selesai', $html);
        $this->assertStringNotContainsString('12.000+', $html);
        $this->assertStringNotContainsString('4,9', $html);
    }

    public function test_texts_equal_to_defaults_are_stored_empty(): void
    {
        $this->post('/admin/tampilan/beranda', $this->home(['hero_subtitle' => config('store.hero.subtitle')]))->assertSessionHas('success');

        $this->assertNull(Setting::get('store.hero_subtitle'));
        $this->assertSame('Kampus', StoreProfile::text('campus_title'));
    }

    public function test_campus_logos_can_be_added_moved_hidden_and_removed(): void
    {
        $this->post('/admin/tampilan/kampus', ['name' => 'Kampus Uji', 'logo' => UploadedFile::fake()->image('k.png')])->assertSessionHas('success');
        $campuses = StoreProfile::campuses();
        $this->assertCount(11, $campuses);
        $this->assertSame(['Kampus Uji', true], [$campuses[10]['name'], $campuses[10]['uploaded']]);

        $this->patch('/admin/tampilan/kampus/10/urut', ['direction' => 'up']);
        $this->assertSame('Kampus Uji', StoreProfile::campuses()[9]['name']);
        $this->patch('/admin/tampilan/kampus/0/urut', ['direction' => 'up'])->assertNotFound();

        $path = StoreProfile::campuses()[9]['path'];
        $this->delete('/admin/tampilan/kampus/9')->assertSessionHas('success', 'Logo Kampus Uji dihapus.');
        Storage::disk('uploads')->assertMissing($path);
        $this->assertCount(10, StoreProfile::campuses());

        $this->assertStringContainsString('Universitas Indonesia', $this->get('/')->getContent());
        $this->post('/admin/tampilan/beranda', $this->home(['campus_enabled' => false]));
        $this->assertStringNotContainsString('Universitas Indonesia', $this->get('/')->getContent());
    }

    public function test_socials_are_saved_in_order_and_only_active_ones_show(): void
    {
        $socials = collect(config('store.social_platforms'))->keys()->map(fn ($p) => ['platform' => $p, 'url' => '', 'active' => false])->all();
        $socials[0] = ['platform' => 'instagram', 'url' => 'https://instagram.com/tokouji', 'active' => true];
        $socials[1] = ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@tokouji', 'active' => false];
        $socials = [$socials[1], $socials[0], ...array_slice($socials, 2)];

        $this->put('/admin/tampilan/sosmed', ['socials' => $socials])->assertSessionHas('success');

        $this->assertSame(['tiktok', 'instagram'], array_slice(array_column(StoreProfile::socials(), 'platform'), 0, 2));
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('https://instagram.com/tokouji', $html);
        $this->assertStringNotContainsString('tiktok.com/@tokouji', $html);
        $this->assertStringNotContainsString('tiktok.com/@proakses', $html);

        $socials[2]['active'] = true; // Facebook tanpa URL
        $this->put('/admin/tampilan/sosmed', ['socials' => $socials])->assertSessionHasErrors(['socials.2.url' => 'Isi URL dulu, atau matikan saklarnya.']);
        $socials[2]['url'] = 'javascript:alert(1)';
        $this->put('/admin/tampilan/sosmed', ['socials' => $socials])->assertSessionHasErrors('socials.2.url');
    }

    public function test_whatsapp_comes_from_env_and_only_its_visibility_and_order_are_saved(): void
    {
        config(['proakses.whatsapp' => '081277788899']);
        $socials = collect(config('store.social_platforms'))->keys()->map(fn ($p) => ['platform' => $p, 'url' => '', 'active' => false])->all();
        $wa = array_search('whatsapp', array_column($socials, 'platform'));
        $socials[$wa] = ['platform' => 'whatsapp', 'url' => 'https://evil.test', 'active' => true];
        $socials = [$socials[$wa], ...array_values(array_filter($socials, fn ($s) => $s['platform'] !== 'whatsapp'))];

        $this->put('/admin/tampilan/sosmed', ['socials' => $socials])->assertSessionHas('success');

        $first = StoreProfile::socials()[0];
        $this->assertSame(['whatsapp', 'https://wa.me/6281277788899', true], [$first['platform'], $first['url'], $first['active']]);
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('aria-label="WhatsApp"', $html);
        $this->assertStringNotContainsString('evil.test', $html);

        config(['proakses.whatsapp' => null]);
        $this->put('/admin/tampilan/sosmed', ['socials' => $socials])->assertSessionHasErrors('socials.0.url');
    }

    public function test_a_saved_list_without_whatsapp_keeps_the_whatsapp_icon(): void
    {
        config(['proakses.whatsapp' => '081277788899']);
        Setting::put('store.socials', json_encode([['platform' => 'tiktok', 'url' => 'https://tiktok.com/@x', 'active' => true]]));

        $this->assertContains('whatsapp', array_column(StoreProfile::activeSocials(), 'platform'));
    }
}
