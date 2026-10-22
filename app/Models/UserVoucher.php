<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Voucher yang sudah diklaim user (dompet voucher). */
#[Fillable(['user_id', 'voucher_id', 'claimed_at'])]
class UserVoucher extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
