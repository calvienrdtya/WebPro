<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Support\AiSettings;
use App\Support\Whatsapp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Chat AI di toko: percakapan (user login dilanjutkan lintas perangkat, tamu per sesi browser), batas harian dari
 * menu Perilaku, gambar pelanggan (disimpan privat, dihapus otomatis), pemanggilan AI lewat PromptBuilder yang sama
 * dengan simulasi admin, dan log setiap pesan.
 */
class ChatService
{
    public const SESSION_KEY = 'ai_chat';

    public function __construct(private readonly PromptBuilder $prompts) {}

    /** AI dipakai widget toko bila saklar Gunakan AI menyala, koneksi lengkap, dan pop up Tanya AI tampil. */
    public static function available(): bool
    {
        return AiSettings::enabled() && AiSettings::configured() && Whatsapp::aiPopupEnabled();
    }

    /** Batas harian untuk jenis penanya ini: [pesan, gambar]; 0 = ditutup. */
    public static function limits(?User $user): array
    {
        $b = AiSettings::behavior();

        return $user ? [$b['user_messages'], $b['user_images']] : [$b['guest_messages'], $b['guest_images']];
    }

    /** Pemakaian hari ini: [pesan, gambar]. User dihitung per akun, tamu per IP. */
    public function usedToday(?User $user, ?string $ip): array
    {
        $query = AiMessage::query()->where('role', 'user')->where('ai_messages.created_at', '>=', today())
            ->whereHas('conversation', fn ($q) => $user ? $q->withTrashed()->where('user_id', $user->id) : $q->withTrashed()->whereNull('user_id')->where('ip', $ip));

        return [(clone $query)->count(), (clone $query)->where('has_image', true)->count()];
    }

    /** Percakapan aktif penanya, atau null bila belum ada. */
    public function current(?User $user, string $sessionKey): ?AiConversation
    {
        if ($user) {
            return AiConversation::query()->where('user_id', $user->id)
                ->where('last_message_at', '>=', now()->subHours(AiSettings::advanced('conversation_idle_hours')))
                ->latest('last_message_at')->first();
        }

        return AiConversation::query()->whereNull('user_id')->where('guest_key', $sessionKey)->latest('id')->first();
    }

    /** Pesan percakapan untuk ditampilkan ulang di widget (teks saja; gambar ditandai). */
    public function transcript(?AiConversation $conversation): array
    {
        if (! $conversation) {
            return [];
        }

        return $conversation->messages()->orderBy('id')->get()
            ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => (string) $m->content, 'image' => $m->has_image, 'showWa' => $m->show_wa])
            ->all();
    }

    /**
     * Satu giliran chat. Mengembalikan {reply, showWa, actions} atau {fallback: true} (widget memakai jawaban kata kunci)
     * atau {limited: true, reply, ...} bila batas harian habis.
     */
    public function send(?User $user, string $sessionKey, ?string $ip, string $message, ?UploadedFile $image): array
    {
        if (! static::available()) {
            return ['fallback' => true];
        }

        [$maxMessages, $maxImages] = static::limits($user);
        [$usedMessages, $usedImages] = $this->usedToday($user, $ip);

        if ($usedMessages >= $maxMessages) {
            return $this->limited($user, $maxMessages, imagesOnly: false);
        }

        if ($image && $usedImages >= $maxImages) {
            return $this->limited($user, $maxImages, imagesOnly: true);
        }

        $conversation = $this->current($user, $sessionKey) ?? AiConversation::create([
            'user_id' => $user?->id,
            'guest_key' => $user ? null : $sessionKey,
            'ip' => $ip,
        ]);

        $history = $conversation->messages()->where(fn ($q) => $q->where('role', 'user')->orWhere('status', 'ok'))
            ->latest('id')->limit(max(1, AiSettings::advanced('history_messages')))->get()->reverse()
            ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => trim(($m->content ?? '').($m->has_image ? "\n[pelanggan mengirim gambar]" : ''))])
            ->values()->all();

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
            'has_image' => $image !== null,
            'image_path' => $image ? Storage::disk(AiMessage::DISK)->putFile('chat/'.$conversation->id, $image) : null,
        ]);

        $images = $image ? ['data:'.$image->getMimeType().';base64,'.base64_encode((string) file_get_contents($image->getRealPath()))] : [];
        $model = $image ? AiSettings::visionModel() : AiSettings::chatModel();
        $started = microtime(true);

        try {
            $reply = AiClient::fromSettings()->chat($model, $this->prompts->forChat($history, $message, $images, $user), AiSettings::behavior()['max_tokens']);
            $showWa = ReplyActions::wantsWhatsapp($reply->content);

            $this->log($conversation, [
                'role' => 'assistant', 'content' => $reply->content, 'status' => 'ok', 'model' => $reply->model,
                'prompt_tokens' => $reply->promptTokens, 'completion_tokens' => $reply->completionTokens,
                'ms' => (int) round((microtime(true) - $started) * 1000), 'show_wa' => $showWa,
            ]);

            return ['reply' => $reply->content, 'showWa' => $showWa, 'actions' => ReplyActions::links($reply->content, $user)];
        } catch (AiException $e) {
            // Jawaban kata kunci di widget; pertanyaan tetap tercatat dan dihitung supaya batas harian tidak bisa diakali.
            $this->log($conversation, ['role' => 'assistant', 'status' => 'fallback', 'error' => Str::limit($e->getMessage(), 250), 'model' => $model, 'ms' => (int) round((microtime(true) - $started) * 1000)]);

            return ['fallback' => true];
        }
    }

    private function limited(?User $user, int $limit, bool $imagesOnly): array
    {
        $b = AiSettings::behavior();
        $template = $imagesOnly ? $b['limit_image_message'] : ($user ? $b['limit_message'] : $b['limit_message_guest']);
        $text = PromptBuilder::fill($template, ['batas' => $limit, 'url_login' => route('login')]);

        return ['limited' => true, 'reply' => $text, 'showWa' => ! $imagesOnly && Whatsapp::waPopupEnabled(), 'actions' => $user || $imagesOnly ? [] : [['t' => 'Login', 'href' => route('login')]]];
    }

    private function log(AiConversation $conversation, array $attributes): void
    {
        $conversation->messages()->create($attributes);
        $conversation->forceFill([
            'messages_count' => $conversation->messages()->count(),
            'last_message_at' => now(),
        ])->save();
    }
}
