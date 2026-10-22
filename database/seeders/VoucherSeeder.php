<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

/** Voucher contoh. Memakai firstOrCreate: voucher yang sudah ada (mis. sudah diedit admin) tidak ditimpa. */
class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $vouchers = [
            ['code' => 'HEMAT50', 'title' => 'Diskon 50% Semua Produk', 'description' => 'Berlaku untuk semua produk premium.', 'discount_type' => DiscountType::Percent, 'discount_value' => 50, 'max_discount' => 50000],
            ['code' => 'POTONG25K', 'title' => 'Potongan Rp25.000', 'description' => 'Potongan langsung untuk pembelian minimal Rp100.000.', 'discount_type' => DiscountType::Fixed, 'discount_value' => 25000, 'min_purchase' => 100000],
            ['code' => 'LANGSUNG10', 'title' => 'Diskon 10% Kode Langsung', 'description' => null, 'discount_type' => DiscountType::Percent, 'discount_value' => 10, 'requires_claim' => false, 'is_listed' => false],
        ];

        foreach ($vouchers as $voucher) {
            Voucher::firstOrCreate(['code' => $voucher['code']], $voucher + ['is_active' => true, 'applies_to_all' => true]);
        }
    }
}
