<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris Log Aktivitas. Ditulis oleh App\Support\Activity; tidak pernah diubah setelah dibuat. */
class ActivityLog extends Model
{
    public const ACTORS = [
        'admin' => 'Admin',
        'user' => 'Pelanggan',
        'guest' => 'Tamu',
        'system' => 'Sistem',
    ];

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Aktivitas yang menyangkut satu user: dilakukan olehnya, atau user itu sendiri yang diubah. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('actor_id', $userId)
            ->orWhere(fn ($q) => $q->where('subject_type', 'user')->where('subject_id', $userId)));
    }
}
