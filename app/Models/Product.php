<?php

namespace App\Models;

use App\Support\VersionedUrl;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\ProductCategory;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'slug', 'category', 'account_type', 'description', 'highlights', 'specs',
    'price', 'discount_percent', 'service_fee', 'image', 'meta_title', 'meta_description', 'sort_order', 'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'account_type' => AccountType::class,
            'highlights' => 'array',
            'specs' => 'array',
            'price' => 'integer',
            'discount_percent' => 'integer',
            'service_fee' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function warranties(): BelongsToMany
    {
        return $this->belongsToMany(Warranty::class, 'product_warranty');
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Penawaran varian untuk produk ini, urut dari yang terpendek. Butuh productVariants.variant ter-load;
     * produk dipasang ke tiap baris supaya harga 'ambil dari produk' tidak memicu query tambahan.
     *
     * @return Collection<int, ProductVariant>
     */
    public function offers(bool $onlyActive = false): Collection
    {
        return $this->productVariants
            ->each(fn (ProductVariant $pv) => $pv->setRelation('product', $this))
            ->when($onlyActive, fn (Collection $c) => $c->filter(fn (ProductVariant $pv) => $pv->variant->is_active))
            ->sortBy(fn (ProductVariant $pv) => [$pv->variant->months, $pv->variant_id])
            ->values();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Ringkasan ulasan yang sudah disetujui: jumlah, rata-rata (1 desimal), dan sebaran per bintang.
     *
     * @return array{count: int, average: float, distribution: array<int, int>}
     */
    public function reviewSummary(): array
    {
        $byRating = $this->reviews()->approved()->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');
        $distribution = collect(range(5, 1))->mapWithKeys(fn (int $star) => [$star => (int) ($byRating[$star] ?? 0)])->all();
        $count = array_sum($distribution);
        $sum = array_sum(array_map(fn ($star, $total) => $star * $total, array_keys($distribution), $distribution));

        return ['count' => $count, 'average' => $count ? round($sum / $count, 1) : 0.0, 'distribution' => $distribution];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Menambah `stock_count` (akun tersedia) dan `sold_count` (akun terjual) untuk tampilan toko. */
    public function scopeWithStoreCounts(Builder $query): Builder
    {
        return $query->withCount([
            'accounts as stock_count' => fn (Builder $q) => $q->where('status', AccountStatus::Available),
            'accounts as sold_count' => fn (Builder $q) => $q->where('status', AccountStatus::Sold),
        ]);
    }

    /**
     * Pilihan durasi yang dijual di toko, dari yang terpendek. Tanpa varian aktif, produk dijual 1 bulan dengan harga produk.
     * Butuh productVariants.variant ter-load.
     *
     * @return Collection<int, array{id: int|null, title: string, months: int, list_price: int, final_price: int, discount: int}>
     */
    public function saleOptions(): Collection
    {
        $options = $this->offers(onlyActive: true)->map(fn (ProductVariant $pv) => [
            'id' => $pv->id,
            'title' => $pv->variant->title,
            'months' => $pv->variant->months,
            'list_price' => $pv->list_price,
            'final_price' => $pv->final_price,
            'discount' => (int) $pv->discount,
        ]);

        return $options->isNotEmpty() ? $options->values() : collect([[
            'id' => null,
            'title' => '1 Bulan',
            'months' => 1,
            'list_price' => $this->price,
            'final_price' => $this->final_price,
            'discount' => $this->discount_percent,
        ]]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === ''
            ? $query
            : $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%"));
    }

    /** Harga akhir pelanggan = harga jual dikurangi diskon %; harga coret di toko adalah `price`. */
    protected function finalPrice(): Attribute
    {
        return Attribute::get(fn () => (int) round($this->price * (100 - $this->discount_percent) / 100));
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? VersionedUrl::upload($this->image) : null);
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'produk';
        $candidate = $slug;

        for ($i = 2; static::withTrashed()->where('slug', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $i++) {
            $candidate = "{$slug}-{$i}";
        }

        return $candidate;
    }
}
