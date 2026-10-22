<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menu AI > Percakapan: log chat Tanya AI di toko. Disimpan permanen; hapus = soft delete (tidak tampil lagi).
 * Penanda membantu admin melihat pertanyaan yang belum bisa dijawab AI (jawaban cadangan atau diarahkan ke WhatsApp).
 */
class AiConversationController extends Controller
{
    private const TABS = ['all', 'fallback', 'wa', 'image'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'all';
        $who = in_array($request->query('who'), ['user', 'guest'], true) ? $request->query('who') : 'all';

        $conversations = $this->filtered($q, $who)
            ->with('user:id,name,email')
            ->withCount([
                'messages as user_messages' => fn (Builder $m) => $m->where('role', 'user'),
                'messages as fallback_count' => fn (Builder $m) => $m->where('status', 'fallback'),
                'messages as wa_count' => fn (Builder $m) => $m->where('show_wa', true),
                'messages as image_count' => fn (Builder $m) => $m->where('has_image', true),
            ])
            ->addSelect(['preview' => AiMessage::query()->select('content')->whereColumn('conversation_id', 'ai_conversations.id')->where('role', 'user')->orderBy('id')->limit(1)])
            ->when($tab !== 'all', fn (Builder $c) => $c->whereHas('messages', fn (Builder $m) => match ($tab) {
                'fallback' => $m->where('status', 'fallback'),
                'wa' => $m->where('show_wa', true),
                'image' => $m->where('has_image', true),
            }))
            ->latest('last_message_at')->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (AiConversation $c) => [
                'id' => $c->id,
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name, 'email' => $c->user->email] : null,
                'anonymized' => $c->anonymized_at !== null,
                'ip' => $c->ip,
                'preview' => Str::limit((string) $c->preview, 120) ?: '(gambar)',
                'user_messages' => $c->user_messages,
                'fallback_count' => $c->fallback_count,
                'wa_count' => $c->wa_count,
                'image_count' => $c->image_count,
                'created_at' => $c->created_at->toIso8601String(),
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        $today = AiMessage::query()->where('created_at', '>=', today());

        return Inertia::render('Ai/Conversations', [
            'conversations' => $conversations,
            'filters' => ['q' => $q, 'tab' => $tab, 'who' => $who],
            'counts' => collect(self::TABS)->mapWithKeys(fn ($t) => [$t => $t === 'all'
                ? AiConversation::count()
                : AiConversation::whereHas('messages', fn (Builder $m) => match ($t) {
                    'fallback' => $m->where('status', 'fallback'),
                    'wa' => $m->where('show_wa', true),
                    'image' => $m->where('has_image', true),
                })->count()]),
            'stats' => [
                'conversations_today' => AiConversation::where('last_message_at', '>=', today())->count(),
                'questions_today' => (clone $today)->where('role', 'user')->count(),
                'fallback_today' => (clone $today)->where('status', 'fallback')->count(),
                'tokens_today' => (int) (clone $today)->sum('prompt_tokens') + (int) (clone $today)->sum('completion_tokens'),
            ],
        ]);
    }

    /** Isi lengkap satu percakapan untuk dialog detail. */
    public function show(AiConversation $conversation): JsonResponse
    {
        $conversation->load('user:id,name,email');

        return response()->json([
            'id' => $conversation->id,
            'user' => $conversation->user ? ['name' => $conversation->user->name, 'email' => $conversation->user->email] : null,
            'anonymized' => $conversation->anonymized_at !== null,
            'ip' => $conversation->ip,
            'created_at' => $conversation->created_at->toIso8601String(),
            'messages' => $conversation->messages()->orderBy('id')->get()->map(fn (AiMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'has_image' => $m->has_image,
                'image_url' => $m->image_path ? route('admin.ai.conversations.image', $m) : null,
                'status' => $m->status,
                'error' => $m->error,
                'model' => $m->model,
                'prompt_tokens' => $m->prompt_tokens,
                'completion_tokens' => $m->completion_tokens,
                'ms' => $m->ms,
                'show_wa' => $m->show_wa,
                'created_at' => $m->created_at->toIso8601String(),
            ])->all(),
        ]);
    }

    /** Gambar dari pelanggan (penyimpanan privat), selama belum dihapus otomatis. */
    public function image(AiMessage $message): StreamedResponse
    {
        abort_unless($message->image_path && Storage::disk(AiMessage::DISK)->exists($message->image_path), 404);

        return Storage::disk(AiMessage::DISK)->response($message->image_path);
    }

    public function destroy(AiConversation $conversation): RedirectResponse
    {
        $conversation->delete();

        return back()->with('success', 'Percakapan dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['integer']])['ids'];
        $count = AiConversation::whereKey($ids)->get()->each->delete()->count();

        return back()->with('success', "{$count} percakapan dihapus.");
    }

    /** Cari di isi pesan atau nama/email user; filter penanya user atau tamu. */
    private function filtered(string $q, string $who): Builder
    {
        return AiConversation::query()
            ->when($who === 'user', fn (Builder $c) => $c->whereNotNull('user_id'))
            ->when($who === 'guest', fn (Builder $c) => $c->whereNull('user_id'))
            ->when($q !== '', fn (Builder $c) => $c->where(fn (Builder $w) => $w
                ->whereHas('messages', fn (Builder $m) => $m->where('content', 'like', "%{$q}%"))
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                ->orWhere('ip', $q)));
    }
}
