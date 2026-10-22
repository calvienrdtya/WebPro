<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'sequence', 'due_on', 'account_id', 'cogs', 'delivered_at', 'stock_alerted_at', 'warranty_ends_on'])]
class OrderDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_on' => 'date',
            'cogs' => 'integer',
            'delivered_at' => 'datetime',
            'stock_alerted_at' => 'datetime',
            'warranty_ends_on' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }
}
