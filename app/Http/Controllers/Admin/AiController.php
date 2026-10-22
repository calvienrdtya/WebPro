<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiAdvancedRequest;
use App\Http\Requests\Admin\AiBehaviorRequest;
use App\Http\Requests\Admin\AiConnectionRequest;
use App\Http\Requests\Admin\AiTemplatesRequest;
use App\Models\Setting;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiException;
use App\Services\Ai\AiReply;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\VisionProbe;
use App\Support\AiSettings;
use App\Support\Whatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Menu AI: Koneksi (router, API key, model, tes) dan Perilaku (identitas, instruksi, batas harian). */
class AiController extends Controller
{
    public function connection(): Response
    {
        return Inertia::render('Ai/Connection', [
            'connection' => [
                'enabled' => AiSettings::enabled(),
                'base_url' => AiSettings::baseUrl(),
                'masked_key' => AiSettings::maskedApiKey(),
                'chat_model' => AiSettings::chatModel() ?? '',
                'vision_model' => Setting::get(AiSettings::VISION_MODEL) ?? '',
                'reasoning_effort' => AiSettings::reasoningEffort() ?? '',
                'popup' => Whatsapp::aiPopupEnabled(),
            ],
            'advanced' => [
                ...AiSettings::advancedAll(),
                'strip_prefixes' => implode("\n", AiSettings::stripPrefixes()),
                'whatsapp_triggers' => implode("\n", AiSettings::whatsappTriggers()),
            ],
            'advancedRanges' => collect(config('ai.advanced'))->map(fn ($r) => ['default' => $r[0], 'min' => $r[1], 'max' => $r[2]]),
            'advancedDefaults' => [
                'strip_prefixes' => implode("\n", config('ai.strip_prefixes')),
                'whatsapp_triggers' => implode("\n", config('ai.whatsapp_triggers')),
            ],
            'simulation' => AiSimulationController::props(),
        ]);
    }

    public function updateConnection(AiConnectionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Setting::put(AiSettings::BASE_URL, rtrim($data['base_url'], '/'));
        if ($request->boolean('remove_key')) {
            AiSettings::putApiKey(null);
        } elseif (filled($data['api_key'] ?? null)) {
            AiSettings::putApiKey($data['api_key']);
        }
        Setting::put(AiSettings::CHAT_MODEL, $data['chat_model'] ?? null);
        Setting::put(AiSettings::VISION_MODEL, $data['vision_model'] ?? null);
        Setting::put(AiSettings::REASONING, $data['reasoning_effort'] ?? null);
        Setting::put(AiSettings::ENABLED, $request->boolean('enabled'));

        return back()->with('success', 'Koneksi AI disimpan.');
    }

    /** Daftar model dari router. Memakai URL dan API key yang sedang diketik bila ada, selain itu yang tersimpan. */
    public function models(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_url' => ['nullable', 'url:http,https', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $client = AiClient::fromSettings(rtrim($data['base_url'] ?? '', '/'), $data['api_key'] ?? null);

        try {
            return response()->json(['models' => $client->models()]);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Tes koneksi tersimpan: "connection" (daftar model), "chat" (model chat menjawab), "vision" (model vision membaca
     * kode acak di gambar buatan server; lulus hanya bila kodenya disebut, jadi model yang menebak tidak lolos).
     */
    public function test(Request $request, PromptBuilder $prompts): JsonResponse
    {
        $type = $request->validate(['type' => ['required', 'in:connection,chat,vision']])['type'];
        $client = AiClient::fromSettings();
        $started = microtime(true);
        $elapsed = fn () => (int) round((microtime(true) - $started) * 1000);

        try {
            if ($type === 'connection') {
                $count = count($client->models());

                return response()->json(['ok' => true, 'message' => "Terhubung. {$count} model tersedia.", 'ms' => $elapsed()]);
            }

            $model = $type === 'chat' ? AiSettings::chatModel() : AiSettings::visionModel();
            if (! $model) {
                return response()->json(['ok' => false, 'message' => 'Pilih dan simpan model chat dulu.']);
            }

            if ($type === 'chat') {
                // Prompt yang sama persis dengan chat pelanggan. Model yang membawa persona lain dari penyedianya
                // (mis. asisten coding) tidak menyebut nama asisten dan dinyatakan gagal.
                $name = AiSettings::behavior()['name'];
                $reply = $client->chat($model, $prompts->forChat([], $prompts->testQuestion()), AiSettings::advanced('test_tokens'));
                $ok = mb_stripos($reply->content, $name) !== false;
                $who = $this->answeredBy($model, $reply);

                return $this->testResult($ok, $ok
                    ? "{$who} menjawab sebagai {$name}."
                    : "{$who} menjawab, tetapi tidak menyebut nama {$name}. Kemungkinan penyedia model menambahkan persona sendiri (mis. asisten coding) yang mengalahkan instruksi toko, atau pertanyaan tes di menu Perilaku tidak menanyakan identitas.", $reply, $elapsed());
            }

            ['code' => $code, 'png' => $png] = app(VisionProbe::class)->make();
            $reply = $client->chat($model, $prompts->forVisionTest($png), AiSettings::advanced('test_tokens'));
            $ok = str_contains(preg_replace('/\D+/', '', $reply->content), $code);
            $who = $this->answeredBy($model, $reply);

            return $this->testResult($ok, $ok
                ? "{$who} bisa membaca gambar."
                : "{$who} tidak membaca gambar dengan benar (angka di gambar: {$code}). Pilih model vision lain.", $reply, $elapsed());
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'ms' => $elapsed()]);
        }
    }

    public function behavior(): Response
    {
        return Inertia::render('Ai/Behavior', [
            'behavior' => AiSettings::behavior(),
            'templates' => AiSettings::templates(),
            'defaults' => [
                'instructions' => config('ai.behavior.instructions'),
                'fallback' => config('ai.behavior.fallback'),
                'greeting' => config('ai.behavior.greeting'),
                'limit_message' => config('ai.behavior.limit_message'),
                'limit_message_guest' => config('ai.behavior.limit_message_guest'),
                'limit_image_message' => config('ai.behavior.limit_image_message'),
                'templates' => config('ai.templates'),
            ],
            'placeholders' => config('ai.placeholders'),
        ]);
    }

    public function updateBehavior(AiBehaviorRequest $request): RedirectResponse
    {
        AiSettings::putBehavior($request->validated());

        return back()->with('success', 'Perilaku AI disimpan.');
    }

    public function updateTemplates(AiTemplatesRequest $request): RedirectResponse
    {
        AiSettings::putTemplates($request->validated());

        return back()->with('success', 'Template prompt disimpan.');
    }

    /** Instruksi system lengkap dari isian form (belum disimpan), dengan penanda sudah terisi data nyata. */
    public function preview(Request $request, PromptBuilder $prompts): JsonResponse
    {
        $data = $request->validate([
            'template' => ['required', 'string', 'max:20000'],
            'name' => ['nullable', 'string', 'max:60'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'fallback' => ['nullable', 'string', 'max:500'],
        ]);

        $prompt = $prompts->system(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['prompt' => $prompt, 'tokens' => PromptBuilder::estimateTokens($prompt)]);
    }

    public function updateAdvanced(AiAdvancedRequest $request): RedirectResponse
    {
        $data = $request->validated();
        AiSettings::putAdvanced($data);
        AiSettings::putStripPrefixes($data['strip_prefixes']);
        AiSettings::putWhatsappTriggers($data['whatsapp_triggers']);

        return back()->with('success', 'Pengaturan lanjutan disimpan.');
    }

    /** "Model X", ditambah nama combo bila router menjawab dengan model lain dari yang diminta. */
    private function answeredBy(string $requested, AiReply $reply): string
    {
        // Router bisa membalas tanpa awalan penyedia (mis. "gemini-3-flash" untuk "ag/gemini-3-flash"): itu model yang sama.
        $same = $reply->model === $requested || str_ends_with($requested, '/'.$reply->model);

        return $same ? "Model {$requested}" : "Model {$reply->model} (lewat {$requested})";
    }

    private function testResult(bool $ok, string $message, AiReply $reply, int $ms): JsonResponse
    {
        return response()->json(['ok' => $ok, 'message' => $message, 'reply' => $reply->content, 'usage' => $reply->usage(), 'ms' => $ms]);
    }
}
