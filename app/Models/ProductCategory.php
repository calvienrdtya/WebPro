<?php

namespace App\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /** Kategori yang tampil di toko: punya minimal satu produk aktif. Kategori kosong disembunyikan, tidak dihapus. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereHas('products', fn (Builder $q) => $q->where('is_active', true));
    }

    /** Cari kategori dari nama (huruf besar/kecil diabaikan, cocok juga dengan slug); buat bila belum ada. */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return static::findByName($name) ?? static::create(['name' => $name, 'slug' => static::uniqueSlug($name)]);
    }

    public static function findByName(string $name): ?self
    {
        return static::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->orWhere('slug', Str::slug($name))
            ->first();
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'kategori';
        $candidate = $slug;

        for ($i = 2; static::where('slug', $candidate)->exists(); $i++) {
            $candidate = "{$slug}-{$i}";
        }

        return $candidate;
    }
}
