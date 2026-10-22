<?php

namespace App\Services\Ai;

use App\Support\AiSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien router AI yang kompatibel OpenAI (/v1/models, /v1/chat/completions).
 * Kegagalan apa pun dilempar sebagai AiException berpesan Indonesia yang aman ditampilkan ke admin (tanpa API key).
 */
class AiClient
{
    /**
     * @param  int  $thinkingHeadroom  ruang tambahan di atas batas panjang jawaban: model yang "berpikir" (mis. Gemini 3)
     *                                  menghitung token berpikir di dalam max_tokens; tanpa ruang ini balasan bisa kosong.
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeout = 60,
        private readonly ?string $reasoningEffort = null,
        private readonly int $connectTimeout = 10,
        private readonly int $thinkingHeadroom = 2048,
    ) {}

    /** Klien dari pengaturan tersimpan; URL dan API key bisa diganti (mis. yang sedang diketik di form). */
    public static function fromSettings(?string $baseUrl = null, ?string $apiKey = null, ?int $timeout = null): self
    {
        return new self(
            $baseUrl ?: AiSettings::baseUrl(),
            $apiKey ?: AiSettings::apiKey(),
            $timeout ?? AiSettings::advanced('request_timeout'),
            AiSettings::reasoningEffort(),
            AiSettings::advanced('connect_timeout'),
            AiSettings::advanced('thinking_headroom'),
        );
    }

    /**
     * Daftar id model, urut abjad.
     *
     * @return list<string>
     */
    public function models(): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->timeout($this->timeout)->get($this->baseUrl.'/models'));

        $ids = collect($response->json('data', []))->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '');

        if ($ids->isEmpty()) {
            throw new AiException('Router membalas, tetapi daftar model kosong atau formatnya tidak dikenali.');
        }

        return $ids->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /**
     * Kirim percakapan dan kembalikan teks balasan. $maxTokens = batas panjang jawaban; ruang berpikir ditambahkan sendiri.
     *
     * @param  list<array{role: string, content: string|array}>  $messages
     */
    public function chat(string $model, array $messages, int $maxTokens = 600): AiReply
    {
        $response = $this->send(fn (PendingRequest $http) => $http->timeout($this->timeout)->post($this->baseUrl.'/chat/completions', array_filter([
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens + $this->thinkingHeadroom,
            // Parameter standar OpenAI; tidak dikirim bila "Bawaan" supaya model yang tidak mengenalnya tidak menolak.
            'reasoning_effort' => $this->reasoningEffort,
        ], fn ($v) => $v !== null)));

        $content = $response->json('choices.0.message.content');

        if (is_array($content)) { // sebagian router mengembalikan potongan berjenis
            $content = collect($content)->pluck('text')->filter()->implode('');
        }

        $content = is_string($content) ? ReplyCleaner::clean($content) : null;

        $answeredBy = is_string($response->json('model')) && $response->json('model') !== '' ? $response->json('model') : $model;

        if ($content === null || $content === '') {
            $model = $answeredBy;

            throw new AiException($response->json('choices.0.finish_reason') === 'length'
                ? "Model {$model} kehabisan batas token sebelum sempat menjawab (token habis untuk berpikir). Pilih varian yang berpikir lebih sedikit."
                : "Model {$model} membalas kosong. Coba model lain.");
        }

        $usage = fn (string $key) => is_numeric($response->json("usage.{$key}")) ? (int) $response->json("usage.{$key}") : null;

        return new AiReply($content, $answeredBy, $usage('prompt_tokens'), $usage('completion_tokens'));
    }

    private function send(callable $request): Response
    {
        if (! $this->apiKey) {
            throw new AiException('API key belum diisi.');
        }

        try {
            $response = $request(Http::withToken($this->apiKey)->acceptJson()->connectTimeout($this->connectTimeout));
        } catch (ConnectionException) {
            throw new AiException('Tidak bisa terhubung ke router (alamat salah, server mati, atau waktu habis).');
        }

        if ($response->successful()) {
            return $response;
        }

        $detail = $response->json('error.message') ?? $response->json('error') ?? $response->json('message');
        $detail = is_string($detail) ? ': '.mb_strimwidth($detail, 0, 200, '...') : '';

        throw new AiException(match (true) {
            in_array($response->status(), [401, 403]) => 'API key ditolak router'.$detail,
            $response->status() === 404 => 'Alamat tidak ditemukan (periksa base URL, biasanya diakhiri /v1)'.$detail,
            $response->status() === 429 => 'Router membatasi permintaan (rate limit)'.$detail,
            default => "Router membalas galat {$response->status()}".$detail,
        });
    }
}
