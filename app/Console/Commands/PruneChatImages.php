<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsActivity;
use App\Models\AiMessage;
use App\Support\AiSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'ai:prune-chat-images', description: 'Hapus gambar chat pelanggan yang sudah melewati masa simpan (Pengaturan lanjutan AI)')]
class PruneChatImages extends Command
{
    use RecordsActivity;

    public function handle(): int
    {
        $days = AiSettings::advanced('chat_image_days');
        $count = 0;

        AiMessage::query()->whereNotNull('image_path')->where('created_at', '<', now()->subDays($days))
            ->orderBy('id')->chunkById(200, function ($messages) use (&$count) {
                foreach ($messages as $m) {
                    Storage::disk(AiMessage::DISK)->delete($m->image_path);
                    // Penanda has_image tetap: log masih menunjukkan bahwa pelanggan pernah melampirkan gambar.
                    $m->forceFill(['image_path' => null])->save();
                    $count++;
                }
            });

        $this->info("{$count} gambar chat dihapus (lebih dari {$days} hari).");

        return self::SUCCESS;
    }
}
