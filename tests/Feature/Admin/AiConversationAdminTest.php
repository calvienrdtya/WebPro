<?php

namespace Tests\Feature\Admin;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiConversationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    private function conversation(?User $user, array $messages, array $attrs = []): AiConversation
    {
        $c = AiConversation::create(['user_id' => $user?->id, 'guest_key' => $user ? null : 'sesi', 'ip' => '10.0.0.7', 'last_message_at' => now()] + $attrs);
        foreach ($messages as $m) {
            $c->messages()->create($m);
        }

        return $c;
    }

    public function test_only_admins_can_reach_conversation_routes(): void
    {
        $c = $this->conversation(null, [['role' => 'user', 'content' => 'halo']]);

        auth()->logout();
        $this->get('/admin/ai/percakapan')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get('/admin/ai/percakapan')->assertForbidden();
        $this->getJson("/admin/ai/percakapan/{$c->id}")->assertForbidden();
        $this->delete("/admin/ai/percakapan/{$c->id}")->assertForbidden();
    }

    public function test_index_lists_conversations_with_flags_counts_filters_and_search(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@uji.test']);
        $this->conversation($budi, [
            ['role' => 'user', 'content' => 'Netflix error kode NW-2'],
            ['role' => 'assistant', 'content' => 'Hubungi admin lewat WhatsApp', 'status' => 'ok', 'show_wa' => true, 'prompt_tokens' => 100, 'completion_tokens' => 20],
        ]);
        $this->conversation(null, [
            ['role' => 'user', 'content' => 'ini gambarnya', 'has_image' => true],
            ['role' => 'assistant', 'status' => 'fallback', 'error' => 'timeout'],
        ]);

        $this->get('/admin/ai/percakapan')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Ai/Conversations')
            ->has('conversations.data', 2)
            ->where('counts', ['all' => 2, 'fallback' => 1, 'wa' => 1, 'image' => 1])
            ->where('stats.questions_today', 2)
            ->where('stats.fallback_today', 1)
            ->where('stats.tokens_today', 120));

        $this->get('/admin/ai/percakapan?tab=fallback')->assertInertia(fn ($page) => $page
            ->has('conversations.data', 1)->where('conversations.data.0.user', null)->where('conversations.data.0.image_count', 1));
        $this->get('/admin/ai/percakapan?who=user')->assertInertia(fn ($page) => $page
            ->has('conversations.data', 1)->where('conversations.data.0.user.email', 'budi@uji.test')->where('conversations.data.0.wa_count', 1)
            ->where('conversations.data.0.preview', 'Netflix error kode NW-2'));
        $this->get('/admin/ai/percakapan?q=NW-2')->assertInertia(fn ($page) => $page->has('conversations.data', 1));
        $this->get('/admin/ai/percakapan?q=Budi')->assertInertia(fn ($page) => $page->has('conversations.data', 1));
    }

    public function test_detail_and_private_images(): void
    {
        Storage::disk('local')->put('chat/1/layar.png', 'png');
        $c = $this->conversation(null, [
            ['role' => 'user', 'content' => 'lihat', 'has_image' => true, 'image_path' => 'chat/1/layar.png'],
            ['role' => 'user', 'content' => 'lama', 'has_image' => true, 'image_path' => null],
            ['role' => 'assistant', 'content' => 'Itu layar login', 'status' => 'ok', 'model' => 'gemini', 'ms' => 1200],
        ]);
        $withImage = $c->messages()->whereNotNull('image_path')->first();

        $this->getJson("/admin/ai/percakapan/{$c->id}")->assertOk()
            ->assertJsonPath('ip', '10.0.0.7')
            ->assertJsonPath('messages.0.image_url', route('admin.ai.conversations.image', $withImage))
            ->assertJsonPath('messages.1.has_image', true)
            ->assertJsonPath('messages.1.image_url', null)
            ->assertJsonPath('messages.2.model', 'gemini');

        $this->get(route('admin.ai.conversations.image', $withImage))->assertOk();
        $this->get(route('admin.ai.conversations.image', $c->messages()->where('content', 'lama')->first()))->assertNotFound();
    }

    public function test_delete_is_soft_and_bulk_delete_works(): void
    {
        $a = $this->conversation(null, [['role' => 'user', 'content' => 'a']]);
        $b = $this->conversation(null, [['role' => 'user', 'content' => 'b']]);
        $c = $this->conversation(null, [['role' => 'user', 'content' => 'c']]);

        $this->delete("/admin/ai/percakapan/{$a->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($a);
        $this->delete('/admin/ai/percakapan/massal', ['ids' => [$b->id, $c->id]])->assertSessionHas('success', '2 percakapan dihapus.');
        $this->assertSame(0, AiConversation::count());
        $this->assertSame(3, AiMessage::count()); // pesan tetap ada untuk hitungan batas harian
    }

    public function test_deleting_an_account_anonymizes_its_conversations_and_removes_images(): void
    {
        $user = User::factory()->create(['password' => 'Rahasia#2026kuat']);
        Storage::disk('local')->put('chat/9/a.png', 'png');
        $c = $this->conversation($user, [['role' => 'user', 'content' => 'halo', 'has_image' => true, 'image_path' => 'chat/9/a.png']]);

        $this->actingAs($user)->delete('/profil', ['confirm' => 'HAPUS', 'password' => 'Rahasia#2026kuat'])->assertRedirect(route('home'));

        $c->refresh();
        $this->assertNull($c->user_id);
        $this->assertNull($c->ip);
        $this->assertNotNull($c->anonymized_at);
        $this->assertNull($c->messages()->first()->image_path);
        Storage::disk('local')->assertMissing('chat/9/a.png');
        $this->assertSame('halo', $c->messages()->first()->content);

        // Hapus dari menu User admin juga menganonimkan.
        $other = User::factory()->create();
        $d = $this->conversation($other, [['role' => 'user', 'content' => 'x']]);
        $this->actingAs($this->admin)->delete("/admin/user/{$other->id}")->assertSessionHas('success');
        $this->assertNotNull($d->fresh()->anonymized_at);
        $this->assertNull($d->fresh()->ip);
    }
}
