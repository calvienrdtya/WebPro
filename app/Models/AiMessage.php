<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pesan di percakapan chat AI. Gambar pelanggan ada di disk privat `local` (folder chat/). */
#[Fillable(['conversation_id', 'role', 'content', 'has_image', 'image_path', 'status', 'error', 'model', 'prompt_tokens', 'completion_tokens', 'ms', 'show_wa'])]
class AiMessage extends Model
{
    public const DISK = 'local';

    protected function casts(): array
    {
        return [
            'has_image' => 'boolean',
            'show_wa' => 'boolean',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
