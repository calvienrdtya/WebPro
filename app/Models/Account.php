<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['product_id', 'email', 'password', 'profile', 'pin', 'cogs', 'status', 'started_at', 'active_days', 'sold_to', 'sold_at', 'note'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'password' => 'encrypted',
            'cogs' => 'integer',
            'sold_at' => 'datetime',
            'started_at' => 'date',
            'active_days' => 'integer',
            'expires_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Account $account) {
            // Kedaluwarsa selalu turunan dari tanggal mulai + masa aktif, bukan diisi manual.
            $account->started_at ??= today();
            $account->active_days ??= config('proakses.account_days');
            $account->expires_at = $account->started_at->copy()->addDays((int) $account->active_days)->toDateString();

            if (! $account->isDirty('status')) {
                return;
            }

            if ($account->status === AccountStatus::Sold) {
                $account->sold_at ??= now();
            } elseif ($account->status === AccountStatus::Available) {
                // Kembali ke stok: hapus jejak pembeli.
                $account->sold_to = null;
                $account->sold_at = null;
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(OrderDelivery::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', AccountStatus::Available);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === ''
            ? $query
            : $query->where(fn (Builder $q) => $q
                ->where('email', 'like', "%{$term}%")
                ->orWhere('profile', 'like', "%{$term}%")
                ->orWhere('note', 'like', "%{$term}%"));
    }
}
