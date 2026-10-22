<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'title', 'description', 'discount_type', 'discount_value', 'max_discount', 'min_purchase',
    'starts_on', 'ends_on', 'quota', 'per_user_limit', 'requires_claim', 'is_listed', 'is_active', 'applies_to_all',
])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    public const STATUSES = ['running', 'scheduled', 'ended', 'inactive'];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'max_discount' => 'integer',
            'min_purchase' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'quota' => 'integer',
            'used_count' => 'integer',
            'per_user_limit' => 'integer',
            'requires_claim' => 'boolean',
            'is_listed' => 'boolean',
            'is_active' => 'boolean',
            'applies_to_all' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'voucher_product');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function isExhausted(): bool
    {
        return $this->quota !== null && $this->used_count >= $this->quota;
    }

    /** running = bisa dipakai sekarang; scheduled = belum mulai; ended = lewat tanggal atau kuota habis; inactive = dimatikan admin. */
    public function status(): string
    {
        $today = today();

        return match (true) {
            ! $this->is_active => 'inactive',
            $this->starts_on !== null && $this->starts_on->gt($today) => 'scheduled',
            ($this->ends_on !== null && $this->ends_on->lt($today)) || $this->isExhausted() => 'ended',
            default => 'running',
        };
    }

    /**
     * Potongan (rupiah) untuk subtotal harga produk yang memenuhi syarat voucher ini; 0 bila syarat tidak terpenuhi.
     * Hanya menghitung potongan, bukan status voucher (cek `status()` terpisah). Tidak pernah melebihi subtotal.
     */
    public function discountFor(int $subtotal): int
    {
        if ($subtotal <= 0 || ($this->min_purchase !== null && $subtotal < $this->min_purchase)) {
            return 0;
        }

        $discount = $this->discount_type === DiscountType::Percent
            ? intdiv($subtotal * $this->discount_value, 100)
            : $this->discount_value;

        if ($this->discount_type === DiscountType::Percent && $this->max_discount !== null) {
            $discount = min($discount, $this->max_discount);
        }

        return min($discount, $subtotal);
    }

    public function scopeApplicableTo(Builder $query, Product|int $product): Builder
    {
        $id = $product instanceof Product ? $product->id : $product;

        return $query->where(fn (Builder $q) => $q
            ->where('applies_to_all', true)
            ->orWhereHas('products', fn (Builder $p) => $p->whereKey($id)));
    }

    /** Filter berdasarkan status() di SQL. */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        $today = today()->toDateString();
        $exhausted = fn (Builder $q) => $q->whereNotNull('quota')->whereColumn('used_count', '>=', 'quota');

        return match ($status) {
            'inactive' => $query->where('is_active', false),
            'scheduled' => $query->where('is_active', true)->where('starts_on', '>', $today),
            'ended' => $query->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
                ->where(fn (Builder $q) => $q->where('ends_on', '<', $today)->orWhere($exhausted)),
            'running' => $query->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
                ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
                ->whereNot($exhausted),
            default => $query,
        };
    }
}
