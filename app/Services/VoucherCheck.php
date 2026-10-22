<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;

/** Satu-satunya tempat aturan "boleh tidak voucher ini dipakai untuk produk ini", dipakai cek di halaman produk dan checkout. */
class VoucherCheck
{
    /**
     * @return array{voucher: ?Voucher, discount: int, error: ?string}
     */
    public static function check(string $rawCode, Product $product, int $subtotal, ?User $user, bool $lock = false): array
    {
        $code = strtoupper(preg_replace('/\s+/', '', $rawCode));
        $fail = fn (string $message) => ['voucher' => null, 'discount' => 0, 'error' => $message];

        $voucher = Voucher::where('code', $code)->when($lock, fn ($q) => $q->lockForUpdate())->first();

        if (! $voucher || $voucher->status() !== 'running') {
            return $fail('Kode voucher tidak valid atau sudah berakhir.');
        }

        if (! $voucher->applies_to_all && ! $voucher->products()->whereKey($product->id)->exists()) {
            return $fail('Voucher ini tidak berlaku untuk produk ini.');
        }

        if ($voucher->min_purchase !== null && $subtotal < $voucher->min_purchase) {
            return $fail('Minimal pembelian Rp'.number_format($voucher->min_purchase, 0, ',', '.').' untuk memakai voucher ini.');
        }

        if ($voucher->requires_claim) {
            if (! $user) {
                return $fail('Masuk dan klaim voucher ini dulu di halaman Voucher.');
            }

            if (! UserVoucher::where('user_id', $user->id)->where('voucher_id', $voucher->id)->exists()) {
                return $fail('Voucher ini perlu diklaim dulu di halaman Voucher.');
            }
        }

        if ($user && $voucher->per_user_limit !== null) {
            // Pesanan yang gagal (kedaluwarsa, ditolak, dibatalkan) tidak menghabiskan jatah.
            $used = Order::where('user_id', $user->id)->where('voucher_id', $voucher->id)
                ->whereNotIn('status', array_map(fn (OrderStatus $s) => $s->value, array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->isClosedUnpaid())))
                ->count();

            if ($used >= $voucher->per_user_limit) {
                return $fail('Kamu sudah mencapai batas pemakaian voucher ini.');
            }
        }

        $discount = $voucher->discountFor($subtotal);

        return $discount > 0
            ? ['voucher' => $voucher, 'discount' => $discount, 'error' => null]
            : $fail('Voucher ini tidak memberi potongan untuk pesanan ini.');
    }
}
