<?php

namespace App\Models;

use Database\Factories\WarrantyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'price', 'duration_days', 'is_active', 'applies_to_all'])]
class Warranty extends Model
{
    /** @use HasFactory<WarrantyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
            'applies_to_all' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_warranty');
    }

    /** Garansi yang berlaku untuk produk ini: yang ditandai semua produk, atau yang dipasang ke produk ini. */
    public function scopeApplicableTo(Builder $query, Product|int $product): Builder
    {
        $id = $product instanceof Product ? $product->id : $product;

        return $query->where(fn (Builder $q) => $q
            ->where('applies_to_all', true)
            ->orWhereHas('products', fn (Builder $p) => $p->whereKey($id)));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
