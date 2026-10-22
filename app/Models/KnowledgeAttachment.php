<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Lampiran entri pengetahuan (gambar atau PDF), disimpan di disk privat `local` dan hanya dibuka admin. */
#[Fillable(['kind', 'path', 'original_name', 'mime', 'size', 'pages'])]
class KnowledgeAttachment extends Model
{
    protected $table = 'ai_knowledge_attachments';

    protected function casts(): array
    {
        return ['size' => 'integer', 'pages' => 'integer'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class, 'entry_id');
    }

    /** Isi file sebagai data URL untuk dikirim ke model vision (PDF juga lewat image_url, sudah teruji di router). */
    public function dataUrl(): ?string
    {
        $content = Storage::disk('local')->get($this->path);

        return $content === null ? null : 'data:'.$this->mime.';base64,'.base64_encode($content);
    }
}
