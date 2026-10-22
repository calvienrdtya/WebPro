<?php

namespace App\Models;

use Database\Factories\VariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Templat durasi (mis. "3 Bulan"); harga per produk ada di ProductVariant. */
#[Fillable(['title', 'months', 'is_active'])]
class Variant extends Model
{
    /** @use HasFactory<VariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
