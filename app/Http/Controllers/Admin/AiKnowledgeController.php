<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KnowledgeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeEntryRequest;
use App\Jobs\ProcessKnowledgeEntry;
use App\Models\KnowledgeAttachment;
use App\Models\KnowledgeEntry;
use App\Models\Product;
use App\Services\Ai\KnowledgeBase;
use App\Services\Ai\PromptBuilder;
use App\Support\AiSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menu AI > Pengetahuan: data toko otomatis (saklar per bagian) dan entri buatan admin. Entri yang dirapikan AI
 * selalu masuk "Perlu ditinjau" dulu; hanya entri Siap dan aktif yang dikirim ke AI.
 */
class AiKnowledgeController extends Controller
{
    private const TABS = ['semua', 'aktif', 'review', 'processing', 'failed', 'nonaktif'];

    public function index(Request $request, KnowledgeBase $knowledge, PromptBuilder $prompts): Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'semua';
        $search = trim((string) $request->query('q'));

        $entries = KnowledgeEntry::query()
            ->with(['products:id,name', 'attachments'])
            ->search($search)
            ->tap(fn (Builder $q) => $this->applyTab($q, $tab))
            ->orderByDesc('is_builtin')->latest('updated_at')->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (KnowledgeEntry $e) => $this->present($e));

        $systemTokens = PromptBuilder::estimateTokens($prompts->system());
        $overhead = AiSettings::advanced('provider_overhead');

        return Inertia::render('Ai/Knowledge', [
            'entries' => $entries,
            'filters' => ['q' => $search, 'tab' => $tab],
            'counts' => collect(self::TABS)->mapWithKeys(fn ($t) => [$t => KnowledgeEntry::query()->search($search)->tap(fn (Builder $q) => $this->applyTab($q, $t))->count()]),
            'stats' => [
                'active' => KnowledgeEntry::usable()->count(),
                'review' => KnowledgeEntry::where('status', KnowledgeStatus::Review)->count(),
                'processing' => KnowledgeEntry::where('status', KnowledgeStatus::Processing)->count(),
                'failed' => KnowledgeEntry::where('status', KnowledgeStatus::Failed)->count(),
            ],
            'sections' => $knowledge->sections(),
            'size' => [
                'knowledge' => PromptBuilder::estimateTokens($knowledge->forPrompt()),
                'system' => $systemTokens,
                'overhead' => $overhead,
                'total' => $systemTokens + $overhead,
                'limit' => AiSettings::advanced('full_context_limit'),
            ],
            // Entri yang menunggu antrean lebih dari 2 menit berarti cron schedule:run belum berjalan.
            'queueStalled' => KnowledgeEntry::where('status', KnowledgeStatus::Processing)->where('updated_at', '<', now()->subMinutes(2))->exists(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'limits' => collect(['max_images', 'image_mb', 'pdf_mb', 'pdf_pages'])->mapWithKeys(fn ($k) => [$k => AiSettings::advanced($k)]),
        ]);
    }

    public function store(KnowledgeEntryRequest $request): RedirectResponse
    {
        $entry = DB::transaction(function () use ($request) {
            $entry = KnowledgeEntry::create([...$request->safe()->only(['title', 'raw_text', 'use_ai', 'with_qa']), 'created_by' => $request->user()->id]);
            $entry->products()->sync($request->validated('products'));
            $this->storeAttachments($entry, $request->file('attachments', []));

            return $entry;
        });

        $this->startProcessing($entry);

        return back()->with('success', $entry->use_ai ? 'Entri ditambahkan dan sedang dirapikan AI.' : 'Entri ditambahkan. Tinjau lalu setujui agar dipakai AI.');
    }

    /** Ubah judul, produk, teks mentah, isi Markdown, saklar, dan lampiran. Status tidak berubah (pakai Proses ulang atau Setujui). */
    public function update(KnowledgeEntryRequest $request, KnowledgeEntry $entry): RedirectResponse
    {
        DB::transaction(function () use ($request, $entry) {
            $entry->update($request->safe()->only(['title', 'raw_text', 'body', 'use_ai', 'with_qa']));
            $entry->products()->sync($request->validated('products'));

            foreach ($entry->attachments()->whereIn('id', $request->validated('remove_attachments'))->get() as $attachment) {
                Storage::disk('local')->delete($attachment->path);
                $attachment->delete();
            }
            $this->storeAttachments($entry, $request->file('attachments', []));
        });

        KnowledgeBase::forget();

        return back()->with('success', 'Entri disimpan.');
    }

    /** Proses ulang dari teks mentah dan lampiran; isi Markdown lama diganti hasil baru (tetap ditinjau dulu). */
    public function reprocess(KnowledgeEntry $entry): RedirectResponse
    {
        abort_if($entry->is_builtin, 422, 'Entri bawaan diubah langsung, tidak diproses AI.');

        $this->startProcessing($entry);

        return back()->with('success', $entry->use_ai ? 'Entri sedang dirapikan ulang oleh AI.' : 'Isi entri diganti dengan teks mentah. Tinjau lalu setujui.');
    }

    /** Setujui hasil tinjauan: status Siap dan langsung aktif. */
    public function approve(KnowledgeEntry $entry): RedirectResponse
    {
        if (trim((string) $entry->body) === '' || $entry->status === KnowledgeStatus::Processing) {
            return back()->with('error', 'Entri belum punya isi yang bisa disetujui.');
        }

        $entry->forceFill(['status' => KnowledgeStatus::Ready, 'is_active' => true, 'error' => null])->save();
        KnowledgeBase::forget();

        return back()->with('success', "\"{$entry->title}\" disetujui dan dipakai AI.");
    }

    public function toggle(KnowledgeEntry $entry): RedirectResponse
    {
        if ($entry->status !== KnowledgeStatus::Ready) {
            return back()->with('error', 'Hanya entri yang sudah disetujui yang bisa diaktifkan.');
        }

        $entry->update(['is_active' => ! $entry->is_active]);
        KnowledgeBase::forget();

        return back()->with('success', "\"{$entry->title}\" ".($entry->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(KnowledgeEntry $entry): RedirectResponse
    {
        if ($entry->is_builtin) {
            return back()->with('error', 'Entri bawaan tidak bisa dihapus. Nonaktifkan bila tidak ingin dipakai.');
        }

        $entry->delete();
        KnowledgeBase::forget();

        return back()->with('success', "\"{$entry->title}\" dihapus.");
    }

    public function bulkActive(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'active' => ['required', 'boolean']]);

        $count = KnowledgeEntry::whereIn('id', $data['ids'])->where('status', KnowledgeStatus::Ready)->update(['is_active' => $data['active']]);
        KnowledgeBase::forget();

        return back()->with('success', "{$count} entri ".($data['active'] ? 'diaktifkan.' : 'dinonaktifkan.').($count < count($data['ids']) ? ' Entri yang belum disetujui dilewati.' : ''));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']]);

        $count = KnowledgeEntry::whereIn('id', $data['ids'])->where('is_builtin', false)->get()->each->delete()->count();
        KnowledgeBase::forget();

        return back()->with('success', "{$count} entri dihapus.".($count < count($data['ids']) ? ' Entri bawaan dilewati.' : ''));
    }

    public function attachment(KnowledgeAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name);
    }

    public function toggleSection(Request $request, string $key): RedirectResponse
    {
        abort_unless(array_key_exists($key, config('ai.knowledge_sections')), 404);
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        KnowledgeBase::putSection($key, $enabled);

        return back()->with('success', 'Bagian "'.config("ai.knowledge_sections.{$key}").'" '.($enabled ? 'dipakai AI.' : 'tidak dipakai AI.'));
    }

    /** Teks persis satu bagian data toko otomatis, untuk tombol "Lihat isi". */
    public function section(string $key, KnowledgeBase $knowledge): JsonResponse
    {
        abort_unless(array_key_exists($key, config('ai.knowledge_sections')), 404);

        return response()->json(['text' => $knowledge->sectionText($key)]);
    }

    /** Dengan AI: status Diproses lalu antrean. Tanpa AI: teks mentah langsung jadi isi dan menunggu ditinjau. */
    private function startProcessing(KnowledgeEntry $entry): void
    {
        if ($entry->use_ai) {
            $entry->forceFill(['status' => KnowledgeStatus::Processing, 'error' => null])->save();
            ProcessKnowledgeEntry::dispatch($entry->id);
        } else {
            $entry->forceFill(['body' => trim((string) $entry->raw_text), 'status' => KnowledgeStatus::Review, 'error' => null, 'processed_at' => now()])->save();
        }

        KnowledgeBase::forget();
    }

    /** @param  array<int, UploadedFile>  $files */
    private function storeAttachments(KnowledgeEntry $entry, array $files): void
    {
        foreach ($files as $file) {
            $pdf = $file->getMimeType() === 'application/pdf';

            $entry->attachments()->create([
                'kind' => $pdf ? 'pdf' : 'image',
                'path' => $file->store("knowledge/{$entry->id}", 'local'),
                'original_name' => mb_strimwidth($file->getClientOriginalName(), 0, 250),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'pages' => $pdf ? (KnowledgeEntryRequest::pdfPages($file->getRealPath()) ?: null) : null,
            ]);
        }
    }

    private function applyTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'aktif' => $query->usable(),
            'review' => $query->where('status', KnowledgeStatus::Review),
            'processing' => $query->where('status', KnowledgeStatus::Processing),
            'failed' => $query->where('status', KnowledgeStatus::Failed),
            'nonaktif' => $query->where('status', KnowledgeStatus::Ready)->where('is_active', false),
            default => null,
        };
    }

    private function present(KnowledgeEntry $e): array
    {
        return [
            'id' => $e->id,
            'title' => $e->title,
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'is_active' => $e->is_active,
            'is_builtin' => $e->is_builtin,
            'use_ai' => $e->use_ai,
            'with_qa' => $e->with_qa,
            'raw_text' => $e->raw_text ?? '',
            'body' => $e->body ?? '',
            'error' => $e->error,
            'tokens' => PromptBuilder::estimateTokens((string) $e->body),
            'products' => $e->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'attachments' => $e->attachments->map(fn (KnowledgeAttachment $a) => [
                'id' => $a->id,
                'kind' => $a->kind,
                'name' => $a->original_name,
                'size' => $a->size,
                'pages' => $a->pages,
                'url' => route('admin.ai.knowledge.attachment', $a),
            ])->values(),
            'updated_at' => $e->updated_at?->toIso8601String(),
        ];
    }
}
