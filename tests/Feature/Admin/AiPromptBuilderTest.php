<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Services\Ai\KnowledgeBase;
use App\Services\Ai\PromptBuilder;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Isi pengetahuan diuji di AiKnowledgeTest; di sini cukup kosong.
        $this->mock(KnowledgeBase::class, fn ($m) => $m->shouldReceive('forPrompt')->andReturn(''));
    }

    private function builder(): PromptBuilder
    {
        return app(PromptBuilder::class);
    }

    public function test_the_system_prompt_comes_from_the_saved_template(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        Setting::put(AiSettings::TEMPLATE_PREFIX.'system', '{nama} - {tanggal} - {instruksi} - {pesan_tidak_tahu} - [{pengetahuan}]');
        AiSettings::putBehavior(['name' => 'Mimin', 'instructions' => 'Sopan', 'fallback' => 'Tanya admin', 'max_tokens' => 600, 'guest_messages' => 1, 'guest_images' => 1, 'user_messages' => 1, 'user_images' => 1]);

        $this->assertSame('Mimin - Rabu, 7 Oktober 2026 - Sopan - Tanya admin - []', $this->builder()->system());
    }

    public function test_chat_messages_keep_only_the_latest_history(): void
    {
        Setting::put(AiSettings::ADVANCED_PREFIX.'history_messages', '2');
        $history = [
            ['role' => 'user', 'content' => 'satu'], ['role' => 'assistant', 'content' => 'dua'],
            ['role' => 'user', 'content' => 'tiga'], ['role' => 'assistant', 'content' => 'empat', 'extra' => 'dibuang'],
        ];

        $messages = $this->builder()->forChat($history, 'lima', ['data:image/png;base64,QQ==']);

        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame([['role' => 'user', 'content' => 'tiga'], ['role' => 'assistant', 'content' => 'empat']], array_slice($messages, 1, 2));
        $this->assertSame(['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => 'lima'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,QQ==']],
        ]], $messages[3]);

        Setting::put(AiSettings::ADVANCED_PREFIX.'history_messages', '0');
        $this->assertCount(2, $this->builder()->forChat($history, 'lima'));
    }

    public function test_placeholder_checks(): void
    {
        $this->assertSame(['stok'], PromptBuilder::unknownPlaceholders('system', '{nama} {stok} {nama}'));
        $this->assertSame(['pengetahuan'], PromptBuilder::missingPlaceholders('system', '{nama}'));
        $this->assertSame([], PromptBuilder::missingPlaceholders('test_question', 'Siapa kamu?'));
        $this->assertSame('a {x} b', PromptBuilder::fill('{p} {x} {q}', ['p' => 'a', 'q' => 'b']));
    }
}
