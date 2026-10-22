<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';       // menunggu pembayaran (stok direservasi, timer berjalan)
    case Verifying = 'verifying';   // pelanggan sudah klik "Saya Sudah Bayar", menunggu admin
    case Paid = 'paid';             // lunas, kiriman bulan berikutnya masih berjalan
    case Completed = 'completed';   // lunas dan semua akun sudah terkirim
    case Expired = 'expired';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Verifying => 'Menunggu verifikasi',
            self::Paid => 'Berjalan',
            self::Completed => 'Selesai',
            self::Expired => 'Kedaluwarsa',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Pesanan yang masih menahan stok akun dan kuota voucher. */
    public function holdsResources(): bool
    {
        return in_array($this, [self::Pending, self::Verifying], true);
    }

    /** Pesanan yang tidak jadi (stok dan kuota sudah dilepas). */
    public function isClosedUnpaid(): bool
    {
        return in_array($this, [self::Expired, self::Rejected, self::Cancelled], true);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
