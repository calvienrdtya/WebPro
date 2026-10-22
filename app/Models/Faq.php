<?php

namespace App\Models;

use App\Enums\FaqCategory;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category', 'question', 'answer', 'is_active', 'show_on_home', 'show_on_product'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Pertanyaan baru masuk paling akhir.
        static::creating(function (Faq $faq) {
            if (! $faq->sort_order) {
                $faq->sort_order = (int) static::max('sort_order') + 1;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'category' => FaqCategory::class,
            'is_active' => 'boolean',
            'show_on_home' => 'boolean',
            'show_on_product' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Tukar urutan dengan tetangga terdekat di kategori yang sama; false bila sudah di ujung. */
    public function move(string $direction): bool
    {
        $up = $direction === 'up';

        $neighbor = static::query()
            ->where('category', $this->category->value)
            ->where('sort_order', $up ? '<' : '>', $this->sort_order)
            ->orderBy('sort_order', $up ? 'desc' : 'asc')
            ->first();

        if (! $neighbor) {
            return false;
        }

        [$mine, $theirs] = [$this->sort_order, $neighbor->sort_order];
        $neighbor->forceFill(['sort_order' => $mine])->save();
        $this->forceFill(['sort_order' => $theirs])->save();

        return true;
    }
}
