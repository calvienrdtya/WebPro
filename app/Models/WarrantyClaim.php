<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan klaim garansi. Diisi lewat ClaimService; tidak ada mass assignment dari request. */
class WarrantyClaim extends Model
{
    public const OPEN = 'open';

    public const DONE = 'done';

    public const CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'outside_warranty' => 'boolean',
            'claim_cost' => 'integer',
            'replaced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(OrderDelivery::class, 'delivery_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomerReport::class, 'report_id');
    }

    public function oldAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'old_account_id')->withTrashed();
    }

    public function newAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'new_account_id')->withTrashed();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
