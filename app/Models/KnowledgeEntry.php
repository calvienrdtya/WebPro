<?php

namespace App\Models;

use App\Enums\KnowledgeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Entri pengetahuan AI. `status`, `is_builtin`, `error`, dan `processed_at` diatur sistem (forceFill), bukan dari form.
 */
#[Fillable(['title', 'raw_text', 'body', 'is_active', 'use_ai', 'with_qa', 'created_by'])]
class KnowledgeEntry extends Model
{
    use SoftDeletes;

    protected $table = 'ai_knowledge_entries';

    protected function casts(): array
    {
        return [
            'status' => KnowledgeStatus::class,
            'is_active' => 'boolean',
            'is_builtin' => 'boolean',
            'use_ai' => 'boolean',
            'with_qa' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(KnowledgeAttachment::class, 'entry_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'ai_knowledge_entry_product', 'entry_id', 'product_id');
    }

    /** Entri yang dikirim ke AI: sudah ditinjau (Siap) dan aktif. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', KnowledgeStatus::Ready)->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $query : $query->where(fn (Builder $q) => $q
            ->where('title', 'like', "%{$term}%")
            ->orWhere('raw_text', 'like', "%{$term}%")
            ->orWhere('body', 'like', "%{$term}%"));
    }
}
