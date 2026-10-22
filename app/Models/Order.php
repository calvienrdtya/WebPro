<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** `status` dan kolom verifikasi sengaja tidak mass-assignable: hanya layanan pesanan yang mengubahnya (forceFill). */
#[Fillable([
    'code', 'user_id', 'product_id', 'product_name', 'variant_title', 'months',
    'customer_name', 'customer_email', 'customer_phone',
    'list_price', 'product_price', 'warranty_id', 'warranty_name', 'warranty_days', 'warranty_price',
    'voucher_id', 'voucher_code', 'voucher_discount', 'service_fee', 'total', 'expires_at', 'admin_note',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'months' => 'integer',
            'list_price' => 'integer',
            'product_price' => 'integer',
            'warranty_days' => 'integer',
            'warranty_price' => 'integer',
            'voucher_discount' => 'integer',
            'service_fee' => 'integer',
            'total' => 'integer',
            'expires_at' => 'datetime',
            'renewal_reminded_at' => 'datetime',
            'claimed_paid_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class)->latest('id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(OrderDelivery::class)->orderBy('sequence');
    }

    public function scopeStatus(Builder $query, OrderStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof OrderStatus ? $status->value : $status);
    }

    /** Potongan diskon produk (harga coret dikurangi harga akhir produk). */
    public function productDiscount(): int
    {
        return max(0, $this->list_price - $this->product_price);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [OrderStatus::Paid, OrderStatus::Completed], true);
    }

    public static function generateCode(): string
    {
        // Tanpa huruf/angka yang mudah tertukar (0/O, 1/I).
        do {
            $code = 'PRO'.now()->format('ymd').strtr(Str::upper(Str::random(5)), ['0' => 'K', 'O' => 'M', '1' => 'L', 'I' => 'N']);
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
