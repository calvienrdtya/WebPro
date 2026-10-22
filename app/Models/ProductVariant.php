<?php

namespace App\Models;

use App\Enums\PriceMode;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penawaran satu varian untuk satu produk. Harga:
 *  - from_product: harga jual produk x jumlah bulan, dengan diskon produk;
 *  - custom: `price` dan `discount_percent` milik baris ini.
 * Harga coret di toko adalah list_price; pelanggan membayar final_price.
 */
#[Fillable(['product_id', 'variant_id', 'price_mode', 'price', 'discount_percent'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_mode' => PriceMode::class,
            'price' => 'integer',
            'discount_percent' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** Harga sebelum diskon (harga coret). */
    protected function listPrice(): Attribute
    {
        return Attribute::get(fn () => $this->price_mode === PriceMode::FromProduct
            ? $this->product->price * $this->variant->months
            : (int) $this->price);
    }

    protected function discount(): Attribute
    {
        return Attribute::get(fn () => $this->price_mode === PriceMode::FromProduct
            ? $this->product->discount_percent
            : $this->discount_percent);
    }

    /** Harga akhir yang dibayar pelanggan. */
    protected function finalPrice(): Attribute
    {
        return Attribute::get(fn () => (int) round($this->list_price * (100 - $this->discount) / 100));
    }
}
