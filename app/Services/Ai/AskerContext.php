<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\User;
use App\Support\AiSettings;

/**
 * Isi penanda {data_penanya}: siapa yang bertanya. Tamu mendapat arahan untuk login (template asker_guest),
 * user login mendapat daftar pesanan terakhirnya (template asker_user). Kredensial akun (email, password, PIN)
 * tidak pernah ikut; hanya kode, produk, status, dan tanggal.
 */
class AskerContext
{
    public static function for(?User $user): string
    {
        if ($user === null) {
            return PromptBuilder::fill(AiSettings::template('asker_guest'), ['url_login' => route('login')]);
        }

        $orders = Order::query()->where('user_id', $user->id)->with('deliveries.account')
            ->latest('id')->limit(AiSettings::advanced('asker_orders'))->get();

        return PromptBuilder::fill(AiSettings::template('asker_user'), [
            'nama_pelanggan' => $user->name,
            'pesanan' => $orders->isEmpty() ? '(belum ada pesanan)' : $orders->map(fn (Order $o) => static::line($o))->join("\n"),
            'url_pesanan' => route('orders.index'),
        ]);
    }

    private static function line(Order $o): string
    {
        $date = fn ($d) => $d?->locale('id')->translatedFormat('j M Y');
        $delivered = $o->deliveries->filter(fn (OrderDelivery $d) => $d->delivered_at !== null);

        $parts = [
            $o->code,
            $o->product_name.($o->variant_title ? " ({$o->variant_title})" : ''),
            'status: '.$o->status->label(),
            'dipesan '.$date($o->created_at),
            'dibayar '.($date($o->paid_at) ?? 'belum'),
            "akun terkirim {$delivered->count()} dari {$o->months}",
        ];

        if ($o->status->value === 'paid' && $delivered->count() < $o->months) {
            $next = $o->deliveries->first(fn (OrderDelivery $d) => $d->delivered_at === null);
            $parts[] = $next?->due_on && $next->due_on->lte(today()) ? 'akun berikutnya sedang disiapkan (menunggu stok)' : 'akun berikutnya dijadwalkan '.($date($next?->due_on) ?? '-');
        }

        if ($last = $delivered->last()) {
            if ($last->account?->expires_at) {
                $parts[] = 'akun terakhir aktif sampai '.$date($last->account->expires_at);
            }
            $parts[] = $o->warranty_name && $last->warranty_ends_on ? "garansi {$o->warranty_name} sampai ".$date($last->warranty_ends_on) : 'tanpa garansi aktif';
        }

        if ($o->status->value === 'rejected' && $o->reject_reason) {
            $parts[] = 'alasan ditolak: '.$o->reject_reason;
        }

        return '- '.implode(' | ', $parts);
    }
}
