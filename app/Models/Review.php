<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\PublicName;

#[Fillable(['product_id', 'user_id', 'order_id', 'reviewer_name', 'rating', 'body'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const HIDDEN = 'hidden';

    public const STATUSES = [self::PENDING, self::APPROVED, self::HIDDEN];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::APPROVED);
    }

    /** Nama untuk ditampilkan di toko: nama depan dan inisial nama belakang, mis. "Rizky Anwar" menjadi "Rizky A.". */
    public function displayName(): string
    {
        return PublicName::of($this->reviewer_name, 'Pembeli');
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->displayName(), 0, 1));
    }
}
