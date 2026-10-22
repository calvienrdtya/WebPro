<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Biaya layanan (biaya admin). Hanya satu yang aktif dalam satu waktu; nominalnya disalin ke setiap pesanan baru. */
#[Fillable(['name', 'amount', 'is_active'])]
class ServiceFee extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Biaya yang sedang aktif, atau null bila tidak ada. */
    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->orderBy('id')->first();
    }

    /** Nominal (rupiah) yang berlaku untuk pesanan baru; 0 bila tidak ada biaya aktif. */
    public static function current(): int
    {
        return (int) (static::active()?->amount ?? 0);
    }

    /** Biaya untuk produk tertentu: biaya khusus produk bila diisi (0 = tanpa biaya), selain itu biaya yang aktif. */
    public static function forProduct(Product $product): int
    {
        return $product->service_fee ?? static::current();
    }

    /** Menyimpan biaya; bila hasilnya aktif, biaya lain otomatis dinonaktifkan. Mengembalikan nama biaya yang ikut dinonaktifkan. */
    public function saveExclusive(array $attributes): array
    {
        return DB::transaction(function () use ($attributes) {
            $this->fill($attributes)->save();

            if (! $this->is_active) {
                return [];
            }

            $others = static::query()->where('is_active', true)->whereKeyNot($this->getKey())->get();
            $others->each->update(['is_active' => false]);

            return $others->pluck('name')->all();
        });
    }
}
