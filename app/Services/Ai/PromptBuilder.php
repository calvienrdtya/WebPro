<?php

namespace App\Services\Ai;

use App\Models\KnowledgeAttachment;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Support\AiSettings;
use App\Support\Whatsapp;

/**
 * Satu-satunya tempat menyusun pesan untuk AI. Chat pelanggan, Tes chat, dan Uji coba memakai forChat() yang sama,
 * jadi yang dites admin persis yang diterima pelanggan. Semua teks berasal dari template di menu Perilaku
 * (bawaan: config/ai.php); kelas ini hanya mengisi penanda {..} dan menyusun urutan pesan.
 */
class PromptBuilder
{
    public function __construct(private readonly KnowledgeBase $knowledge) {}

    /**
     * Instruksi system lengkap. $overrides dipakai Pratinjau untuk isian form yang belum disimpan.
     * $asker = user yang bertanya (null = tamu); mengisi {data_penanya} yang sengaja ditaruh di akhir template.
     *
     * @param  array{template?: string, name?: string, instructions?: string, fallback?: string}  $overrides
     */
    public function system(array $overrides = [], ?User $asker = null): string
    {
        $behavior = AiSettings::behavior();
        $whatsapp = Whatsapp::number();

        return static::fill($overrides['template'] ?? AiSettings::template('system'), [
            'nama' => $overrides['name'] ?? $behavior['name'],
            'tanggal' => now()->locale('id')->translatedFormat('l, j F Y'),
            'instruksi' => $overrides['instructions'] ?? $behavior['instructions'],
            'pesan_tidak_tahu' => $overrides['fallback'] ?? $behavior['fallback'],
            'whatsapp' => $whatsapp ? '+'.$whatsapp : '-',
            'pengetahuan' => $this->knowledge->forPrompt(),
            'url_lapor' => route('reports.create'),
            'data_penanya' => AskerContext::for($asker),
        ]);
    }

    /**
     * Pesan lengkap untuk satu giliran chat. Instruksi dan pengetahuan selalu di depan (bagian yang jarang berubah,
     * supaya cache token router terpakai), lalu riwayat yang dipotong ke N pesan terakhir, lalu pesan baru.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  list<string>  $images  data URL gambar (data:image/...;base64,...)
     * @return list<array{role: string, content: string|array}>
     */
    public function forChat(array $history, string $message, array $images = [], ?User $asker = null): array
    {
        $keep = AiSettings::advanced('history_messages');
        $history = $keep > 0 ? array_slice($history, -$keep) : [];

        return [
            ['role' => 'system', 'content' => $this->system(asker: $asker)],
            ...array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history),
            ['role' => 'user', 'content' => static::withImages($message, $images)],
        ];
    }

    public function testQuestion(): string
    {
        return AiSettings::template('test_question');
    }

    /** @return list<array{role: string, content: array}> */
    public function forVisionTest(string $png): array
    {
        return [['role' => 'user', 'content' => static::withImages(AiSettings::template('vision_test'), ['data:image/png;base64,'.base64_encode($png)])]];
    }

    /**
     * Perintah konversi entri pengetahuan menjadi Markdown: template konversi (+ template tanya-jawab bila diminta)
     * dengan semua lampiran (gambar dan PDF) sebagai image_url.
     *
     * @return list<array{role: string, content: string|array}>
     */
    public function forKnowledge(KnowledgeEntry $entry): array
    {
        $text = static::fill(AiSettings::template('knowledge_convert'), [
            'judul' => $entry->title,
            'produk' => $entry->products->pluck('name')->join(', ') ?: '-',
            'teks' => trim((string) $entry->raw_text) ?: '-',
        ]);

        if ($entry->with_qa) {
            $text .= "\n\n".AiSettings::template('knowledge_qa');
        }

        $files = $entry->attachments->map(fn (KnowledgeAttachment $a) => $a->dataUrl())->filter()->values()->all();

        return [['role' => 'user', 'content' => static::withImages($text, $files)]];
    }

    /** Isi penanda {nama} dst.; penanda yang tidak dikenal dibiarkan apa adanya. */
    public static function fill(string $template, array $values): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0], $template);
    }

    /** @return list<string> nama penanda {xxx} yang dipakai di teks */
    public static function placeholders(string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $m);

        return array_values(array_unique($m[1]));
    }

    /** @return list<string> penanda di template yang tidak dikenal untuk template itu */
    public static function unknownPlaceholders(string $name, string $template): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $template, $m);

        return array_values(array_diff(array_unique($m[1]), config("ai.placeholders.{$name}", [])));
    }

    /** @return list<string> penanda wajib yang tidak ada di template */
    public static function missingPlaceholders(string $name, string $template): array
    {
        return array_values(array_filter(config("ai.required_placeholders.{$name}", []), fn ($p) => ! str_contains($template, '{'.$p.'}')));
    }

    /** Perkiraan kasar jumlah token (sekitar 4 karakter per token). */
    public static function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }

    /** @param  list<string>  $images */
    private static function withImages(string $text, array $images): string|array
    {
        if ($images === []) {
            return $text;
        }

        return [
            ['type' => 'text', 'text' => $text],
            ...array_map(fn ($url) => ['type' => 'image_url', 'image_url' => ['url' => $url]], $images),
        ];
    }
}
