<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\VisionProbe;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAdminTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://router.uji.test/v1';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function connect(string $key = 'sk-rahasia-uji-9f3c', ?string $model = 'model-chat'): void
    {
        Setting::put(AiSettings::BASE_URL, self::URL);
        AiSettings::putApiKey($key);
        Setting::put(AiSettings::CHAT_MODEL, $model);
    }

    private function connection(array $overrides = []): array
    {
        return [
            'enabled' => false,
            'base_url' => self::URL,
            'api_key' => '',
            'remove_key' => false,
            'chat_model' => 'model-chat',
            'vision_model' => '',
            ...$overrides,
        ];
    }

    public function test_guests_and_regular_users_cannot_reach_the_routes(): void
    {
        $routes = [['get', '/admin/ai/koneksi'], ['put', '/admin/ai/koneksi'], ['post', '/admin/ai/model'], ['post', '/admin/ai/tes'], ['get', '/admin/ai/perilaku'], ['put', '/admin/ai/perilaku']];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
    }

    public function test_the_connection_page_never_exposes_the_api_key(): void
    {
        $this->connect();

        $response = $this->get('/admin/ai/koneksi')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Ai/Connection')
            ->where('connection.base_url', self::URL)
            ->where('connection.masked_key', 'sk-r...9f3c')
            ->where('connection.chat_model', 'model-chat')
            ->where('connection.enabled', false));

        $this->assertStringNotContainsString('sk-rahasia-uji-9f3c', $response->getContent());
    }

    public function test_the_api_key_is_stored_encrypted_and_kept_when_left_blank(): void
    {
        $this->put('/admin/ai/koneksi', $this->connection(['api_key' => ' sk-baru-1234 ', 'base_url' => self::URL.'/']))->assertSessionHas('success');

        $this->assertSame('sk-baru-1234', AiSettings::apiKey());
        $this->assertStringNotContainsString('sk-baru-1234', Setting::get(AiSettings::API_KEY));
        $this->assertSame(self::URL, AiSettings::baseUrl());

        $this->put('/admin/ai/koneksi', $this->connection())->assertSessionHas('success');
        $this->assertSame('sk-baru-1234', AiSettings::apiKey());

        $this->put('/admin/ai/koneksi', $this->connection(['remove_key' => true]))->assertSessionHas('success');
        $this->assertNull(AiSettings::apiKey());
    }

    public function test_ai_can_only_be_enabled_with_a_key_and_a_chat_model(): void
    {
        $this->put('/admin/ai/koneksi', $this->connection(['enabled' => true]))->assertSessionHasErrors(['enabled' => 'Isi API key dulu sebelum menyalakan AI.']);
        $this->put('/admin/ai/koneksi', $this->connection(['enabled' => true, 'api_key' => 'sk-x', 'chat_model' => '']))->assertSessionHasErrors(['enabled' => 'Pilih model chat dulu sebelum menyalakan AI.']);
        $this->put('/admin/ai/koneksi', $this->connection(['base_url' => 'bukan url']))->assertSessionHasErrors('base_url');

        $this->put('/admin/ai/koneksi', $this->connection(['enabled' => true, 'api_key' => 'sk-x', 'vision_model' => 'model-vision']))->assertSessionHas('success');
        $this->assertTrue(AiSettings::enabled());
        $this->assertTrue(AiSettings::configured());
        $this->assertSame('model-vision', AiSettings::visionModel());
    }

    public function test_the_vision_model_falls_back_to_the_chat_model(): void
    {
        $this->connect();

        $this->assertSame('model-chat', AiSettings::visionModel());
    }

    public function test_models_are_listed_with_the_typed_key_or_the_saved_one(): void
    {
        Http::fake([self::URL.'/models' => Http::response(['data' => [['id' => 'zeta'], ['id' => 'alfa'], ['id' => 'Beta']]])]);

        $this->postJson('/admin/ai/model', ['base_url' => self::URL, 'api_key' => 'sk-ketik'])->assertOk()->assertExactJson(['models' => ['alfa', 'Beta', 'zeta']]);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk-ketik'));

        $this->connect('sk-simpan');
        $this->postJson('/admin/ai/model', [])->assertOk();
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk-simpan'));
    }

    public function test_router_errors_are_reported_in_indonesian(): void
    {
        $this->postJson('/admin/ai/model', ['base_url' => self::URL])->assertStatus(422)->assertJson(['message' => 'API key belum diisi.']);

        Http::fake([self::URL.'/models' => Http::response(['error' => 'API key required for remote API access'], 401)]);
        $this->connect();
        $this->postJson('/admin/ai/model')->assertStatus(422)->assertJson(['message' => 'API key ditolak router: API key required for remote API access']);
    }

    public function test_the_connection_and_chat_tests(): void
    {
        $this->connect();
        Setting::put(AiSettings::NAME, 'Mimin');
        Http::fake([
            self::URL.'/models' => Http::response(['data' => [['id' => 'a'], ['id' => 'b']]]),
            self::URL.'/chat/completions' => Http::response([
                'model' => 'model-chat',
                'choices' => [['message' => ['content' => ' Halo, saya mimin dari ProAkses. ']]],
                'usage' => ['prompt_tokens' => 377, 'completion_tokens' => 12],
            ]),
        ]);

        $this->postJson('/admin/ai/tes', ['type' => 'connection'])->assertOk()->assertJson(['ok' => true, 'message' => 'Terhubung. 2 model tersedia.']);
        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertOk()->assertJson([
            'ok' => true,
            'message' => 'Model model-chat menjawab sebagai Mimin.',
            'reply' => 'Halo, saya mimin dari ProAkses.',
            'usage' => ['prompt' => 377, 'completion' => 12],
        ]);

        // Instruksi Perilaku ikut dikirim; reasoning_effort tidak dikirim saat Bawaan.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/chat/completions')
            && $r['model'] === 'model-chat'
            && $r['max_tokens'] === 200 + 2048
            && $r['messages'][0]['role'] === 'system'
            && str_starts_with($r['messages'][0]['content'], 'Namamu Mimin. Hari ini ')
            && str_contains($r['messages'][0]['content'], config('ai.behavior.instructions'))
            && $r['messages'][1] === ['role' => 'user', 'content' => config('ai.templates.test_question')]
            && ! isset($r['reasoning_effort']));
    }

    public function test_the_chat_test_fails_when_another_persona_answers_and_names_the_real_model(): void
    {
        $this->connect('sk-x', 'ProAkses_Web');
        Http::fake([self::URL.'/chat/completions' => Http::response([
            'model' => 'gemini-3-flash',
            'choices' => [['message' => ['content' => "I'm Kiro, an AI development environment."]]],
        ])]);

        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertJson([
            'ok' => false,
            'message' => 'Model gemini-3-flash (lewat ProAkses_Web) menjawab, tetapi tidak menyebut nama Asisten ProAkses. Kemungkinan penyedia model menambahkan persona sendiri (mis. asisten coding) yang mengalahkan instruksi toko, atau pertanyaan tes di menu Perilaku tidak menanyakan identitas.',
            'usage' => ['prompt' => null, 'completion' => null],
        ]);
    }

    public function test_a_reply_without_the_provider_prefix_counts_as_the_same_model(): void
    {
        $this->connect('sk-x', 'ag/gemini-3-flash');
        Http::fake([self::URL.'/chat/completions' => Http::response(['model' => 'gemini-3-flash', 'choices' => [['message' => ['content' => 'Saya Asisten ProAkses.']]]])]);

        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertJson(['ok' => true, 'message' => 'Model ag/gemini-3-flash menjawab sebagai Asisten ProAkses.']);
    }

    public function test_the_thinking_level_is_saved_and_sent(): void
    {
        $this->put('/admin/ai/koneksi', $this->connection(['api_key' => 'sk-x', 'reasoning_effort' => 'low']))->assertSessionHas('success');
        $this->assertSame('low', AiSettings::reasoningEffort());
        $this->get('/admin/ai/koneksi')->assertInertia(fn ($page) => $page->where('connection.reasoning_effort', 'low'));

        Http::fake([self::URL.'/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Saya Asisten ProAkses.']]]])]);
        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertJson(['ok' => true]);
        Http::assertSent(fn (Request $r) => $r['reasoning_effort'] === 'low');

        $this->put('/admin/ai/koneksi', $this->connection(['reasoning_effort' => 'tinggi']))->assertSessionHasErrors('reasoning_effort');
        $this->put('/admin/ai/koneksi', $this->connection(['reasoning_effort' => '']))->assertSessionHas('success');
        $this->assertNull(AiSettings::reasoningEffort());
    }

    public function test_an_empty_reply_explains_whether_thinking_used_up_the_budget(): void
    {
        $this->connect('sk-x', 'Combo_Uji');
        Http::fakeSequence(self::URL.'/chat/completions')
            ->push(['model' => 'gemini-3-flash', 'choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']]])
            ->push(['choices' => [['message' => ['content' => null], 'finish_reason' => 'stop']]]);

        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertJson(['ok' => false, 'message' => 'Model gemini-3-flash kehabisan batas token sebelum sempat menjawab (token habis untuk berpikir). Pilih varian yang berpikir lebih sedikit.']);
        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertJson(['ok' => false, 'message' => 'Model Combo_Uji membalas kosong. Coba model lain.']);
    }

    public function test_the_vision_test_passes_only_when_the_code_in_the_image_is_read(): void
    {
        $this->connect();
        Setting::put(AiSettings::VISION_MODEL, 'model-vision');

        $this->app->instance(VisionProbe::class, new class extends VisionProbe
        {
            public function make(): array
            {
                return ['code' => '4827', 'png' => 'png-uji'];
            }
        });
        $reply = 'Angkanya 4827.';
        Http::fake(function (Request $request) use (&$reply) {
            $this->assertSame('model-vision', $request['model']);
            $this->assertSame('data:image/png;base64,'.base64_encode('png-uji'), $request['messages'][0]['content'][1]['image_url']['url']);

            return Http::response(['choices' => [['message' => ['content' => $reply]]]]);
        });

        $this->postJson('/admin/ai/tes', ['type' => 'vision'])->assertOk()->assertJson(['ok' => true, 'message' => 'Model model-vision bisa membaca gambar.']);

        $reply = 'Saya tidak melihat gambar.';
        $this->postJson('/admin/ai/tes', ['type' => 'vision'])->assertOk()->assertJson(['ok' => false]);
    }

    public function test_the_probe_image_is_a_png(): void
    {
        ['code' => $code, 'png' => $png] = (new VisionProbe)->make();

        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
        $this->assertSame([480, 192], array_slice(getimagesizefromstring($png), 0, 2));
    }

    public function test_tests_need_a_saved_key_and_model(): void
    {
        $this->postJson('/admin/ai/tes', ['type' => 'connection'])->assertJson(['ok' => false, 'message' => 'API key belum diisi.']);

        AiSettings::putApiKey('sk-x');
        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertJson(['ok' => false, 'message' => 'Pilih dan simpan model chat dulu.']);
        $this->postJson('/admin/ai/tes', ['type' => 'lain'])->assertStatus(422);
    }

    public function test_the_behaviour_page_shows_defaults(): void
    {
        $this->get('/admin/ai/perilaku')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Ai/Behavior')
            ->where('behavior.name', config('ai.behavior.name'))
            ->where('behavior.instructions', config('ai.behavior.instructions'))
            ->where('behavior.max_tokens', 600)
            ->where('behavior.guest_messages', 20)
            ->where('behavior.guest_images', 2)
            ->where('behavior.user_messages', 30)
            ->where('behavior.user_images', 5));
    }

    public function test_the_behaviour_can_be_saved_with_normalised_numbers(): void
    {
        $this->put('/admin/ai/perilaku', [
            'name' => 'Mimin',
            'instructions' => '',
            'fallback' => 'Tanya admin ya.',
            'max_tokens' => '0800',
            'guest_messages' => '05',
            'guest_images' => '0',
            'user_messages' => '50',
            'user_images' => '3',
            'greeting' => 'Hai, saya {nama}.',
            'limit_message' => config('ai.behavior.limit_message'),
            'limit_message_guest' => 'Habis {batas}, login di {url_login}',
            'limit_image_message' => config('ai.behavior.limit_image_message'),
        ])->assertSessionHas('success');

        $this->assertSame([
            'name' => 'Mimin', 'instructions' => '', 'fallback' => 'Tanya admin ya.', 'max_tokens' => 800,
            'greeting' => 'Hai, saya {nama}.', 'limit_message' => config('ai.behavior.limit_message'),
            'limit_message_guest' => 'Habis {batas}, login di {url_login}', 'limit_image_message' => config('ai.behavior.limit_image_message'),
            'guest_messages' => 5, 'guest_images' => 0, 'user_messages' => 50, 'user_images' => 3,
        ], AiSettings::behavior());
        // Teks yang sama dengan bawaan disimpan kosong.
        $this->assertNull(Setting::get(AiSettings::LIMIT_MESSAGE));
    }

    public function test_behaviour_validation(): void
    {
        $this->put('/admin/ai/perilaku', [
            'name' => '', 'fallback' => '', 'max_tokens' => '50', 'guest_messages' => '-1', 'guest_images' => 'x', 'user_messages' => '', 'user_images' => '101',
        ])->assertSessionHasErrors(['name', 'fallback', 'max_tokens', 'guest_messages', 'guest_images', 'user_messages', 'user_images', 'greeting', 'limit_message']);
    }

    public function test_the_behaviour_page_lists_templates_with_defaults(): void
    {
        $this->get('/admin/ai/perilaku')->assertInertia(fn ($page) => $page
            ->where('templates.system', config('ai.templates.system'))
            ->where('templates.test_question', config('ai.templates.test_question'))
            ->where('defaults.templates.vision_test', config('ai.templates.vision_test'))
            ->where('placeholders.system', config('ai.placeholders.system')));
    }

    public function test_templates_are_saved_and_used_by_the_tests(): void
    {
        $this->connect();
        $templates = [...config('ai.templates'), 'system' => "Kamu {nama}.\n{pengetahuan}", 'test_question' => 'Siapa namamu?', 'vision_test' => 'Angka berapa?'];
        $this->put('/admin/ai/template', $templates)->assertSessionHas('success');
        $this->assertSame($templates, AiSettings::templates());

        Http::fake([self::URL.'/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Saya Asisten ProAkses.']]]])]);
        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertJson(['ok' => true]);
        $this->postJson('/admin/ai/tes', ['type' => 'vision']);

        Http::assertSent(fn (Request $r) => is_string($r['messages'][0]['content']) && str_starts_with($r['messages'][0]['content'], "Kamu Asisten ProAkses.\n## Produk") && $r['messages'][1]['content'] === 'Siapa namamu?');
        Http::assertSent(fn (Request $r) => is_array($r['messages'][0]['content']) && $r['messages'][0]['content'][0] === ['type' => 'text', 'text' => 'Angka berapa?']);
    }

    public function test_a_template_equal_to_the_default_is_stored_empty(): void
    {
        $this->put('/admin/ai/template', config('ai.templates'))->assertSessionHas('success');

        $this->assertNull(Setting::get(AiSettings::TEMPLATE_PREFIX.'system'));
        $this->assertSame(config('ai.templates.system'), AiSettings::template('system'));
    }

    public function test_template_validation(): void
    {
        $this->put('/admin/ai/template', [...config('ai.templates'), 'system' => 'Halo {nama} {stok}', 'test_question' => 'Hai {nama}', 'vision_test' => ''])
            ->assertSessionHasErrors([
                'system' => 'Penanda tidak dikenal: {stok}. Yang bisa dipakai: {nama}, {tanggal}, {instruksi}, {pesan_tidak_tahu}, {whatsapp}, {pengetahuan}, {url_lapor}, {data_penanya}. Penanda wajib tidak ada: {pengetahuan}.',
                'test_question' => 'Penanda tidak dikenal: {nama}. Template ini tidak memakai penanda.',
                'vision_test',
            ]);

        $this->put('/admin/ai/template', [...config('ai.templates'), 'system' => 'Tanpa data', 'test_question' => 'a', 'vision_test' => 'b'])
            ->assertSessionHasErrors(['system' => 'Penanda wajib tidak ada: {pengetahuan}.']);
    }

    public function test_the_preview_fills_placeholders_from_unsaved_values(): void
    {
        config(['proakses.whatsapp' => '081277788899']);

        $this->postJson('/admin/ai/pratinjau', [
            'template' => "{nama}|{instruksi}|{pesan_tidak_tahu}|{whatsapp}|{tanggal}|{pengetahuan}|{lain}",
            'name' => 'Mimin', 'instructions' => 'Ramah ya', 'fallback' => 'Tanya admin',
        ])->assertOk()->assertJson(fn ($json) => $json
            ->where('prompt', fn ($p) => str_starts_with($p, 'Mimin|Ramah ya|Tanya admin|+6281277788899|') && str_contains($p, '|## Produk, harga, dan stok') && str_ends_with($p, '|{lain}'))
            ->where('tokens', fn ($t) => $t > 0));
    }

    public function test_advanced_settings_are_saved_validated_and_used(): void
    {
        $this->connect();
        $this->get('/admin/ai/koneksi')->assertInertia(fn ($page) => $page
            ->where('advanced.thinking_headroom', 2048)
            ->where('advanced.strip_prefixes', 'skipped:')
            ->where('advancedRanges.history_messages', ['default' => 10, 'min' => 0, 'max' => 50]));

        $defaults = collect(config('ai.advanced'))->map(fn ($r) => (string) $r[0])->all();
        $valid = [...$defaults, 'thinking_headroom' => '04000', 'request_timeout' => '30', 'connect_timeout' => '5', 'test_tokens' => '300', 'history_messages' => '0', 'strip_prefixes' => "skipped:\n\n  catatan: \nskipped:"];
        $this->put('/admin/ai/lanjutan', $valid)->assertSessionHas('success');
        $this->assertSame(['thinking_headroom' => 4000, 'request_timeout' => 30, 'connect_timeout' => 5, 'test_tokens' => 300, 'history_messages' => 0], array_slice(AiSettings::advancedAll(), 0, 5));
        $this->assertSame(['skipped:', 'catatan:'], AiSettings::stripPrefixes());

        Http::fake([self::URL.'/chat/completions' => Http::response(['choices' => [['message' => ['content' => "Saya Asisten ProAkses.\n---\n* Catatan: abaikan"]]]])]);
        $this->postJson('/admin/ai/tes', ['type' => 'chat'])->assertJson(['ok' => true, 'reply' => 'Saya Asisten ProAkses.']);
        Http::assertSent(fn (Request $r) => $r['max_tokens'] === 300 + 4000);

        $this->put('/admin/ai/lanjutan', [...$valid, 'request_timeout' => '1', 'history_messages' => '99', 'thinking_headroom' => 'x'])
            ->assertSessionHasErrors(['request_timeout', 'history_messages', 'thinking_headroom']);
    }

    public function test_out_of_range_stored_values_are_clamped(): void
    {
        Setting::put(AiSettings::ADVANCED_PREFIX.'history_messages', '500');

        $this->assertSame(50, AiSettings::advanced('history_messages'));
    }
}
