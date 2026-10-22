<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Models\Account;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://router.uji.test/v1';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
    }

    private function enable(): void
    {
        Setting::put(AiSettings::BASE_URL, self::URL);
        AiSettings::putApiKey('sk-uji');
        Setting::put(AiSettings::CHAT_MODEL, 'ProAkses_Web');
        Setting::put(AiSettings::VISION_MODEL, 'ag/gemini-3-flash');
        Setting::put(AiSettings::ENABLED, true);
    }

    private function fakeReply(string $content = 'Halo kak, ada yang bisa dibantu?'): void
    {
        Http::fake([self::URL.'/chat/completions' => Http::response([
            'model' => 'gemini-3.7-flash', 'choices' => [['message' => ['content' => $content]]],
            'usage' => ['prompt_tokens' => 3000, 'completion_tokens' => 20],
        ])]);
    }

    /** Isi pesan system yang dikirim ke router pada permintaan terakhir. */
    private function sentSystem(): string
    {
        return Http::recorded()->last()[0]->data()['messages'][0]['content'];
    }

    public function test_without_ai_the_widget_falls_back_to_keywords_and_nothing_is_logged(): void
    {
        $this->postJson('/chat', ['message' => 'halo'])->assertOk()->assertExactJson(['fallback' => true]);
        $this->assertSame(0, AiConversation::count());

        $this->get('/')->assertOk()->assertSee('"aiEndpoint":""', false);
    }

    public function test_guest_question_is_answered_logged_and_told_to_log_in_for_order_questions(): void
    {
        $this->enable();
        $this->fakeReply('Untuk cek pesanan, silakan login dulu di '.route('login'));

        $this->postJson('/chat', ['message' => 'pesanan saya sudah dikirim?'])->assertOk()
            ->assertJsonPath('actions.0.t', 'Login')
            ->assertJsonPath('actions.0.href', route('login'));

        $system = $this->sentSystem();
        $this->assertStringContainsString('Penanya belum login', $system);
        $this->assertStringContainsString(route('login'), $system);
        $this->assertStringContainsString(route('reports.create'), $system);

        $conversation = AiConversation::sole();
        $this->assertNull($conversation->user_id);
        $this->assertNotNull($conversation->guest_key);
        $this->assertSame(['user', 'assistant'], $conversation->messages()->orderBy('id')->pluck('role')->all());
        $this->assertSame('ok', $conversation->messages()->where('role', 'assistant')->value('status'));

        $this->get('/')->assertSee(str_replace('/', '\\/', route('chat.send')), false);
        $this->getJson('/chat/riwayat')->assertJsonCount(2, 'messages')->assertJsonPath('messages.0.content', 'pesanan saya sudah dikirim?');
    }

    public function test_logged_in_user_gets_only_their_own_orders_without_credentials(): void
    {
        $this->enable();
        $this->fakeReply('Pesanan kamu sudah selesai.');
        $user = User::factory()->create(['name' => 'Budi Santoso']);
        $product = Product::factory()->create(['name' => 'Netflix Premium']);
        $mine = Order::factory()->status(OrderStatus::Completed)->create(['user_id' => $user->id, 'product_id' => $product->id, 'paid_at' => now()->subDays(3)]);
        $account = Account::factory()->create(['product_id' => $product->id, 'email' => 'rahasia@akun.test', 'password' => 'SandiRahasia9']);
        $mine->deliveries()->create(['sequence' => 1, 'account_id' => $account->id, 'delivered_at' => now()->subDays(3), 'warranty_ends_on' => today()->addDays(4)]);
        $other = Order::factory()->create();

        $this->actingAs($user)->postJson('/chat', ['message' => 'status pesanan saya?'])->assertOk()->assertJsonPath('reply', 'Pesanan kamu sudah selesai.');

        $system = $this->sentSystem();
        $this->assertStringContainsString('Budi Santoso', $system);
        $this->assertStringContainsString($mine->code, $system);
        $this->assertStringContainsString('Selesai', $system);
        $this->assertStringNotContainsString($other->code, $system);
        $this->assertStringNotContainsString('rahasia@akun.test', $system);
        $this->assertStringNotContainsString('SandiRahasia9', $system);
        $this->assertSame($user->id, AiConversation::sole()->user_id);
    }

    public function test_a_logged_in_user_continues_the_same_conversation_from_another_session(): void
    {
        $this->enable();
        $this->fakeReply();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/chat', ['message' => 'halo'])->assertOk();
        $this->flushSession();
        $this->actingAs($user)->getJson('/chat/riwayat')->assertJsonCount(2, 'messages');
        $this->postJson('/chat', ['message' => 'lanjut'])->assertOk();

        $this->assertSame(1, AiConversation::count());
        // Riwayat dari server ikut dikirim ke AI.
        $sent = Http::recorded()->last()[0]->data()['messages'];
        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($sent, 'role'));

        // Lewat masa lanjut percakapan: mulai percakapan baru.
        $this->travel(25)->hours();
        $this->postJson('/chat', ['message' => 'besoknya'])->assertOk();
        $this->assertSame(2, AiConversation::count());
    }

    public function test_guests_have_their_own_conversation_per_browser_session(): void
    {
        $this->enable();
        $this->fakeReply();

        $this->postJson('/chat', ['message' => 'halo'])->assertOk();
        $this->flushSession();
        $this->getJson('/chat/riwayat')->assertJsonCount(0, 'messages');
    }

    public function test_daily_limits_for_guests_and_users(): void
    {
        $this->enable();
        $this->fakeReply();
        $this->assertSame(20, AiSettings::behavior()['guest_messages']);

        $guest = AiConversation::create(['guest_key' => 'lain', 'ip' => '127.0.0.1']);
        foreach (range(1, 20) as $_) {
            $guest->messages()->create(['role' => 'user', 'content' => 'x']);
        }

        $this->postJson('/chat', ['message' => 'halo'])->assertOk()
            ->assertJsonPath('limited', true)->assertJsonPath('actions.0.t', 'Login');
        $this->assertStringContainsString('20', $this->postJson('/chat', ['message' => 'halo'])->json('reply'));
        Http::assertNothingSent();

        // User login dihitung per akun, bukan per IP, dengan batasnya sendiri.
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/chat', ['message' => 'halo'])->assertOk()->assertJsonMissingPath('limited');

        Setting::put(AiSettings::LIMITS['user_messages'], '1');
        $this->postJson('/chat', ['message' => 'lagi'])->assertJsonPath('limited', true)->assertJsonPath('actions', []);
    }

    public function test_images_go_to_the_vision_model_are_kept_privately_and_pruned_later(): void
    {
        $this->enable();
        $this->fakeReply('Itu layar login Netflix.');

        $this->post('/chat', ['message' => 'ini kenapa?', 'image' => UploadedFile::fake()->image('layar.png')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('reply', 'Itu layar login Netflix.');

        Http::assertSent(function (Request $request) {
            $content = $request->data()['messages'][1]['content'];

            return $request->data()['model'] === 'ag/gemini-3-flash'
                && $content[0]['text'] === 'ini kenapa?'
                && str_starts_with($content[1]['image_url']['url'], 'data:image/png;base64,');
        });

        $message = AiMessage::where('role', 'user')->sole();
        $this->assertTrue($message->has_image);
        Storage::disk('local')->assertExists($message->image_path);

        $this->travel(31)->days();
        $this->artisan('ai:prune-chat-images')->assertSuccessful();
        Storage::disk('local')->assertMissing($message->image_path);
        $this->assertNull($message->fresh()->image_path);
        $this->assertTrue($message->fresh()->has_image);
    }

    public function test_image_rules_and_image_limit(): void
    {
        $this->enable();
        $this->fakeReply();

        $this->post('/chat', ['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertJsonValidationErrors('image');
        $this->postJson('/chat', [])->assertJsonValidationErrors('message');

        Setting::put(AiSettings::LIMITS['guest_images'], '0');
        $this->post('/chat', ['message' => 'lihat', 'image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])
            ->assertJsonPath('limited', true);
        $this->get('/')->assertSee('"imagesEnabled":false', false);
        Http::assertNothingSent();
    }

    public function test_ai_failure_falls_back_but_is_logged_and_counted(): void
    {
        $this->enable();
        Http::fake([self::URL.'/chat/completions' => Http::response(['error' => ['message' => 'down']], 500)]);

        $this->postJson('/chat', ['message' => 'halo'])->assertOk()->assertExactJson(['fallback' => true]);

        $this->assertSame('fallback', AiMessage::where('role', 'assistant')->value('status'));
        $this->assertSame(1, AiMessage::where('role', 'user')->count());
    }

    public function test_messages_per_minute_are_throttled(): void
    {
        $this->enable();
        $this->fakeReply();
        Setting::put(AiSettings::ADVANCED_PREFIX.'chat_per_minute', '2');

        $this->postJson('/chat', ['message' => '1'])->assertOk();
        $this->postJson('/chat', ['message' => '2'])->assertOk();
        $this->postJson('/chat', ['message' => '3'])->assertStatus(429)->assertJsonPath('limited', true);
    }
}
