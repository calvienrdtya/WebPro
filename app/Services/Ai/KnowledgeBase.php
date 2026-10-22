<?php

namespace App\Services\Ai;

use App\Models\KnowledgeEntry;
use App\Models\Setting;
use App\Support\AiSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Isi penanda {pengetahuan}: bagian data toko otomatis yang menyala, lalu entri pengetahuan yang Siap dan aktif
 * (entri bawaan seperti Kebijakan toko lebih dulu). Disimpan di cache selama "knowledge_cache_seconds" dan
 * dikosongkan setiap kali admin mengubah pengetahuan.
 */
class KnowledgeBase
{
    public const CACHE_KEY = 'ai.knowledge.prompt';

    public const SECTION_PREFIX = 'ai.kb.';

    public function __construct(private readonly ShopKnowledge $shop) {}

    public function forPrompt(): string
    {
        $seconds = AiSettings::advanced('knowledge_cache_seconds');

        return $seconds > 0 ? Cache::remember(self::CACHE_KEY, $seconds, fn () => $this->build()) : $this->build();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function sectionEnabled(string $key): bool
    {
        return Setting::bool(self::SECTION_PREFIX.$key, true);
    }

    public static function putSection(string $key, bool $enabled): void
    {
        Setting::put(self::SECTION_PREFIX.$key, $enabled);
        static::forget();
    }

    public function sectionText(string $key): string
    {
        return $this->shop->section($key);
    }

    /** @return list<array{key: string, label: string, enabled: bool, tokens: int}> */
    public function sections(): array
    {
        return collect(config('ai.knowledge_sections'))->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'enabled' => static::sectionEnabled($key),
            'tokens' => PromptBuilder::estimateTokens($this->shop->section($key)),
        ])->values()->all();
    }

    private function build(): string
    {
        $sections = collect(array_keys(config('ai.knowledge_sections')))
            ->filter(fn ($key) => static::sectionEnabled($key))
            ->map(fn ($key) => $this->shop->section($key));

        $entries = KnowledgeEntry::usable()->whereNotNull('body')->orderByDesc('is_builtin')->orderBy('id')->pluck('body')
            ->map(fn ($body) => trim($body))->filter();

        return $sections->concat($entries)->join("\n\n");
    }
}
