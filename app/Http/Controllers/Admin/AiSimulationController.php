<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiException;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\ReplyActions;
use App\Support\AiSettings;
use App\Support\Whatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simulasi chat di menu AI > Koneksi: panel widget toko yang sama persis, tetapi dijawab AI memakai pengaturan tersimpan (template,
 * pengetahuan, model). Tanpa batas harian dan tanpa log percakapan; setiap jawaban membawa info debug untuk admin.
 */
class AiSimulationController extends Controller
{
    /** Data panel simulasi di halaman Koneksi. */
    public static function props(): array
    {
        $wa = Whatsapp::waPopupEnabled();

        return [
            'widget' => [
                'waEnabled' => $wa,
                'waNumber' => $wa ? (string) Whatsapp::number() : '',
                'waMessage' => Whatsapp::chatConfig()['waMessage'] ?? '',
                'assistantName' => AiSettings::behavior()['name'],
                'greeting' => PromptBuilder::fill(AiSettings::behavior()['greeting'], ['nama' => AiSettings::behavior()['name']]),
                'imagesEnabled' => true,
                'imageMaxMb' => AiSettings::advanced('chat_image_mb'),
            ],
            'status' => [
                'enabled' => AiSettings::enabled(),
                'popup' => Whatsapp::aiPopupEnabled(),
                'max_tokens' => AiSettings::behavior()['max_tokens'],
                'history_messages' => AiSettings::advanced('history_messages'),
            ],
        ];
    }

    /**
     * Kontrak sama dengan widget toko: multipart {message, image?, history (JSON)} -> {reply, showWa, actions} atau
     * {fallback: true} (widget lalu memakai jawaban kata kunci). `as` = penanya yang disimulasikan: tamu, atau akun admin
     * yang sedang login (dengan pesanannya sendiri). Gambar tidak disimpan. `debug` hanya untuk admin.
     */
    public function chat(Request $request, PromptBuilder $prompts): JsonResponse
    {
        if (is_string($request->input('history'))) {
            $request->merge(['history' => json_decode($request->input('history'), true) ?: []]);
        }

        $data = $request->validate([
            'message' => ['nullable', 'required_without:image', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.AiSettings::advanced('chat_image_mb') * 1024],
            'history' => ['array', 'max:50'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['present', 'nullable', 'string', 'max:4000'],
            'as' => ['nullable', 'in:guest,me'],
        ]);
        $asker = ($data['as'] ?? 'guest') === 'me' ? $request->user() : null;
        $image = $request->file('image');
        $history = array_map(fn ($m) => ['role' => $m['role'], 'content' => (string) $m['content']], $data['history'] ?? []);

        $model = AiSettings::chatModel();
        if (! AiSettings::configured()) {
            return response()->json(['fallback' => true, 'debug' => ['error' => 'API key atau model chat belum diisi di menu Koneksi.', 'fallback' => true]]);
        }

        $started = microtime(true);
        $elapsed = fn () => (int) round((microtime(true) - $started) * 1000);

        try {
            $images = $image ? ['data:'.$image->getMimeType().';base64,'.base64_encode((string) file_get_contents($image->getRealPath()))] : [];
            $model = $image ? AiSettings::visionModel() : $model;
            $reply = AiClient::fromSettings()->chat($model, $prompts->forChat($history, (string) ($data['message'] ?? ''), $images, $asker), AiSettings::behavior()['max_tokens']);

            return response()->json([
                'reply' => $reply->content,
                'showWa' => ReplyActions::wantsWhatsapp($reply->content),
                'actions' => ReplyActions::links($reply->content, $asker),
                'debug' => [
                    'model' => $reply->model,
                    'requested' => $model,
                    'ms' => $elapsed(),
                    'prompt_tokens' => $reply->promptTokens,
                    'completion_tokens' => $reply->completionTokens,
                    'asker' => $asker ? 'akun '.$asker->email : 'tamu',
                ],
            ]);
        } catch (AiException $e) {
            return response()->json(['fallback' => true, 'debug' => ['error' => $e->getMessage(), 'requested' => $model, 'ms' => $elapsed(), 'fallback' => true]]);
        }
    }
}
