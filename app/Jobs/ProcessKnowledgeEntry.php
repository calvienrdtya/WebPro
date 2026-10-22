<?php

namespace App\Jobs;

use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeEntry;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiException;
use App\Services\Ai\KnowledgeBase;
use App\Services\Ai\PromptBuilder;
use App\Support\AiSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Merapikan satu entri pengetahuan dengan model vision (teks + gambar + PDF menjadi Markdown). Hasilnya selalu
 * berstatus "Perlu ditinjau": admin memeriksa dan menyetujui sebelum dipakai AI. Dijalankan antrean database yang
 * diproses cron schedule:run (lihat routes/console.php).
 */
class ProcessKnowledgeEntry implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $entryId)
    {
        $this->timeout = AiSettings::advanced('convert_timeout') + 30;
    }

    public function handle(PromptBuilder $prompts): void
    {
        $entry = KnowledgeEntry::with(['attachments', 'products'])->find($this->entryId);

        if (! $entry || $entry->status !== KnowledgeStatus::Processing) {
            return; // dihapus atau diproses ulang lewat jalur lain sementara menunggu antrean
        }

        $model = AiSettings::visionModel();

        try {
            if (! $model) {
                throw new AiException('Model vision belum dipilih di menu Koneksi.');
            }

            $reply = AiClient::fromSettings(timeout: AiSettings::advanced('convert_timeout'))
                ->chat($model, $prompts->forKnowledge($entry), AiSettings::advanced('convert_tokens'));

            $entry->forceFill(['body' => $reply->content, 'status' => KnowledgeStatus::Review, 'error' => null, 'processed_at' => now()])->save();
        } catch (AiException $e) {
            $this->markFailed($entry, $e->getMessage());
        }
    }

    /** Dipanggil antrean bila job gagal di luar AiException (mis. waktu habis). */
    public function failed(?Throwable $e): void
    {
        if ($entry = KnowledgeEntry::find($this->entryId)) {
            $this->markFailed($entry, 'Pemrosesan berhenti: '.($e ? class_basename($e) : 'galat tidak dikenal').'. Coba Proses ulang.');
        }
    }

    private function markFailed(KnowledgeEntry $entry, string $message): void
    {
        $entry->forceFill(['status' => KnowledgeStatus::Failed, 'error' => $message, 'processed_at' => now()])->save();
        KnowledgeBase::forget();
    }
}
