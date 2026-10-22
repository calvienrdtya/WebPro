<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Satu percakapan chat AI di toko (user login atau tamu). */
#[Fillable(['user_id', 'guest_key', 'ip', 'messages_count', 'last_message_at'])]
class AiConversation extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'messages_count' => 'integer',
            'last_message_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    /**
     * User menghapus akunnya: percakapannya tetap ada untuk statistik, tetapi tidak lagi tertaut ke orang itu
     * (tanpa user, IP, dan kunci sesi) dan gambar yang masih tersimpan dihapus.
     */
    public static function anonymizeUser(int $userId): void
    {
        static::withTrashed()->where('user_id', $userId)->get()->each(function (self $conversation) {
            $conversation->messages()->whereNotNull('image_path')->get()->each(function (AiMessage $m) {
                Storage::disk(AiMessage::DISK)->delete($m->image_path);
                $m->forceFill(['image_path' => null])->save();
            });

            $conversation->forceFill(['user_id' => null, 'ip' => null, 'guest_key' => null, 'anonymized_at' => now()])->save();
        });
    }
}
