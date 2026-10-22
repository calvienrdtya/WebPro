<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Whatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_number_is_normalised_to_international_digits(): void
    {
        foreach (['0812-3456-7890', '+62 812 3456 7890', '6281234567890', '(0812) 3456 7890'] as $raw) {
            config(['proakses.whatsapp' => $raw]);
            $this->assertSame('6281234567890', Whatsapp::number(), $raw);
        }

        foreach ([null, '', '  ', 'abc'] as $empty) {
            config(['proakses.whatsapp' => $empty]);
            $this->assertNull(Whatsapp::number());
            $this->assertSame('#', Whatsapp::url('Halo'));
        }
    }

    public function test_the_url_carries_an_encoded_message(): void
    {
        config(['proakses.whatsapp' => '081234567890']);

        $this->assertSame('https://wa.me/6281234567890', Whatsapp::url());
        $this->assertSame('https://wa.me/6281234567890?text=Halo%20ProAkses%20%26%20tanya', Whatsapp::url('Halo ProAkses & tanya'));
    }

    public function test_every_whatsapp_link_in_the_store_uses_the_env_number_not_the_old_sample(): void
    {
        $product = Product::factory()->create(['slug' => 'netflix']);
        $order = Order::factory()->status(OrderStatus::Completed)->create(); // halaman pesanan lunas menampilkan tautan WhatsApp
        $pages = ['/', '/faq', '/produk', '/produk/netflix', '/voucher', '/top-pelanggan'];

        foreach ($pages as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('6281200000000', $html, $url);
        }

        $this->get('/')->assertSee('https://wa.me/6281777889900', false);
        $this->get('/faq')->assertSee('https://wa.me/6281777889900', false);
        $this->get('/produk/netflix')->assertSee('https://wa.me/6281777889900?text=', false);

        $this->actingAs($order->user)->get("/pesanan/{$order->code}")->assertSee('https://wa.me/6281777889900?text=', false)->assertDontSee('6281200000000', false);
        $this->get('/profil')->assertSee('https://wa.me/6281777889900', false);
    }

    private function chatConfigOf(string $url = '/'): ?array
    {
        $html = $this->get($url)->assertOk()->getContent();

        return preg_match('/window\.PROAKSES_CHAT = (\{.*?\})<\/script>/s', $html, $m) ? json_decode($m[1], true) : null;
    }

    public function test_both_popups_are_on_by_default_on_every_store_page(): void
    {
        foreach (['/', '/produk', '/login', '/faq'] as $url) {
            $config = $this->chatConfigOf($url);
            $this->assertTrue($config['aiEnabled'], $url);
            $this->assertTrue($config['waEnabled'], $url);
            $this->assertSame('6281777889900', $config['waNumber']);
        }
    }

    public function test_the_ai_popup_can_be_switched_off_on_its_own(): void
    {
        Setting::put(Setting::CHAT_AI_ENABLED, false);

        $config = $this->chatConfigOf();

        $this->assertFalse($config['aiEnabled']);
        $this->assertTrue($config['waEnabled']);
    }

    public function test_the_whatsapp_popup_can_be_switched_off_on_its_own_without_touching_other_whatsapp_links(): void
    {
        Setting::put(Setting::CHAT_WA_ENABLED, false);

        $config = $this->chatConfigOf();

        $this->assertTrue($config['aiEnabled']);
        $this->assertFalse($config['waEnabled']);
        $this->assertSame('', $config['waNumber']);
        $this->get('/faq')->assertSee('https://wa.me/6281777889900', false);
    }

    public function test_the_widget_is_not_loaded_at_all_when_both_popups_are_off(): void
    {
        Setting::put(Setting::CHAT_AI_ENABLED, false);
        Setting::put(Setting::CHAT_WA_ENABLED, false);

        $this->get('/')->assertOk()->assertDontSee('PROAKSES_CHAT', false);
    }

    public function test_without_a_number_only_the_ai_popup_remains(): void
    {
        config(['proakses.whatsapp' => null]);

        $config = $this->chatConfigOf();

        $this->assertTrue($config['aiEnabled']);
        $this->assertFalse($config['waEnabled']);

        Setting::put(Setting::CHAT_AI_ENABLED, false);
        $this->get('/')->assertDontSee('PROAKSES_CHAT', false);
    }

    public function test_the_mobile_buy_bar_has_the_id_the_widget_uses_to_move_up(): void
    {
        Product::factory()->create(['slug' => 'netflix']);

        $this->get('/produk/netflix')->assertSee('id="buy-bar"', false);
    }

    public function test_the_admin_sees_both_switches_and_the_number_in_settings(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin/pengaturan')->assertInertia(fn ($page) => $page->where('chat', ['ai' => true, 'wa' => true, 'number' => '6281777889900']));
    }

    public function test_each_switch_changes_only_its_own_setting_and_takes_effect_in_the_store(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->put('/admin/pengaturan/chat', ['chat_ai_enabled' => false])->assertSessionHas('success', 'Pop up Tanya AI dimatikan.');
        $this->assertFalse(Setting::bool(Setting::CHAT_AI_ENABLED, true));
        $this->assertTrue(Setting::bool(Setting::CHAT_WA_ENABLED, true), 'saklar WhatsApp tidak ikut berubah');
        $this->assertFalse($this->chatConfigOf()['aiEnabled']);

        $this->put('/admin/pengaturan/chat', ['chat_wa_enabled' => false])->assertSessionHas('success', 'Pop up WhatsApp dimatikan.');
        $this->assertFalse(Setting::bool(Setting::CHAT_AI_ENABLED, true), 'saklar AI tetap mati');
        $this->get('/admin/pengaturan')->assertInertia(fn ($page) => $page->where('chat.ai', false)->where('chat.wa', false));
        $this->get('/')->assertDontSee('PROAKSES_CHAT', false);

        $this->put('/admin/pengaturan/chat', ['chat_ai_enabled' => true, 'chat_wa_enabled' => true])->assertSessionHas('success', 'Pop up Tanya AI dinyalakan. Pop up WhatsApp dinyalakan.');
        $config = $this->chatConfigOf();
        $this->assertTrue($config['aiEnabled'] && $config['waEnabled']);
    }

    public function test_an_empty_request_changes_nothing(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Setting::put(Setting::CHAT_AI_ENABLED, false);

        $this->put('/admin/pengaturan/chat', [])->assertSessionHas('success', 'Tidak ada perubahan.');

        $this->assertFalse(Setting::bool(Setting::CHAT_AI_ENABLED, true));
    }

    public function test_only_admins_can_change_the_popup_switches(): void
    {
        $this->put('/admin/pengaturan/chat', ['chat_ai_enabled' => false])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->put('/admin/pengaturan/chat', ['chat_ai_enabled' => false, 'chat_wa_enabled' => false])->assertForbidden();
        $this->assertTrue(Setting::bool(Setting::CHAT_AI_ENABLED, true));
        $this->assertTrue(Setting::bool(Setting::CHAT_WA_ENABLED, true));
    }
}
