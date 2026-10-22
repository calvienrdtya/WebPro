<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Voucher;

class VoucherPresenter
{
    /** Bentuk data satu kartu voucher (dipakai halaman /voucher dan tab Voucher di profil). */
    public static function present(Voucher $v, string $state): array
    {
        $percent = $v->discount_type === DiscountType::Percent;
        $rp = fn (int $n) => 'Rp'.number_format($n, 0, ',', '.');

        return [
            'id' => $v->id,
            'code' => $v->code,
            'title' => $v->title,
            'description' => $v->description,
            'state' => $state,
            'value' => $percent ? $v->discount_value.'%' : $rp($v->discount_value),
            'label' => $percent ? 'Diskon' : 'Potongan',
            'until' => $v->ends_on ? 'Hingga '.$v->ends_on->translatedFormat('j M Y') : 'Tanpa batas waktu',
            'min_purchase' => $v->min_purchase ? 'Min. belanja '.$rp($v->min_purchase) : null,
            'max_discount' => $percent && $v->max_discount ? 'Maks. potongan '.$rp($v->max_discount) : null,
            'scope' => $v->applies_to_all ? 'Semua produk' : $v->products->sortBy('name')->pluck('name')->join(', ', ' dan '),
            'remaining' => $v->quota !== null ? max(0, $v->quota - $v->used_count) : null,
        ];
    }
}
