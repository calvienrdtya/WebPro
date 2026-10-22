<?php

namespace App\Enums;

/** Cara hitung potongan voucher (bukan jenis voucher): persen dari harga produk atau nominal tetap. */
enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
