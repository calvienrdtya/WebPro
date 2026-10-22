<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Mail\AccountDeliveredMail;
use App\Mail\PaymentClaimedMail;
use App\Mail\PaymentReceivedMail;
use App\Mail\StockShortMail;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\Product;
use App\Models\ServiceFee;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Seluruh perpindahan status pesanan lewat sini, masing-masing dalam transaksi dengan kunci baris
 * agar stok akun dan kuota voucher tidak ganda terpakai.
 */
class OrderService
{
    /**
     * Membuat pesanan baru: harga dihitung ulang di server dan kuota voucher langsung terpakai (dilepas lagi bila
     * pesanan batal). Akun belum dikaitkan; stok hanya diperiksa ada, akun diambil saat admin mengonfirmasi pembayaran.
     *
     * @param  array{option: int, warranty_id: ?int, voucher: ?string, name: string, email: string, phone: string}  $data
     */
    public function place(User $user, Product $product, array $data): Order
    {
        $product->loadMissing('productVariants.variant');
        $options = $product->saleOptions();
        $offer = $options[$data['option']] ?? null;

        if ($offer === null) {
            throw new OrderException('Pilihan durasi tidak valid.');
        }

        $warranties = Warranty::active()->applicableTo($product)->get();
        $warranty = null;

        if ($warranties->isNotEmpty()) {
            $warranty = $warranties->firstWhere('id', $data['warranty_id'] ?? null)
                ?? throw new OrderException('Pilih salah satu garansi dulu.');
        }

        return DB::transaction(function () use ($user, $product, $data, $offer, $warranty) {
            $open = Order::where('user_id', $user->id)->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Verifying->value])->count();

            if ($open >= config('proakses.max_open_orders')) {
                throw new OrderException('Kamu masih punya '.$open.' pesanan yang belum selesai dibayar. Selesaikan atau batalkan dulu sebelum membuat pesanan baru.');
            }

            $voucher = null;
            $discount = 0;

            if (filled($data['voucher'] ?? null)) {
                $check = VoucherCheck::check($data['voucher'], $product, (int) $offer['final_price'], $user, lock: true);

                if ($check['error'] !== null) {
                    throw new OrderException($check['error']);
                }

                [$voucher, $discount] = [$check['voucher'], $check['discount']];
            }

            if (! $this->hasStock($product->id)) {
                throw new OrderException('Maaf, stok produk ini baru saja habis.');
            }

            $fee = ServiceFee::forProduct($product);
            $warrantyPrice = $warranty?->price ?? 0;

            $order = new Order([
                'code' => Order::generateCode(),
                'user_id' => $user->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_title' => $offer['id'] ? $offer['title'] : null,
                'months' => $offer['months'],
                'customer_name' => $data['name'],
                'customer_email' => $data['email'],
                'customer_phone' => $data['phone'],
                'list_price' => $offer['list_price'],
                'product_price' => $offer['final_price'],
                'warranty_id' => $warranty?->id,
                'warranty_name' => $warranty?->name,
                'warranty_days' => $warranty?->duration_days,
                'warranty_price' => $warrantyPrice,
                'voucher_id' => $voucher?->id,
                'voucher_code' => $voucher?->code,
                'voucher_discount' => $discount,
                'service_fee' => $fee,
                'total' => max(0, $offer['final_price'] + $warrantyPrice + $fee - $discount),
                'expires_at' => now()->addMinutes(config('proakses.payment_minutes')),
            ]);
            $order->save();

            for ($i = 1; $i <= $order->months; $i++) {
                $order->deliveries()->create(['sequence' => $i]);
            }

            $voucher?->increment('used_count');

            return $order->load('deliveries');
        });
    }

    /** Pelanggan menekan "Saya Sudah Bayar" (bukti transfer opsional). */
    public function claimPaid(Order $order, ?UploadedFile $proof = null): Order
    {
        $order = DB::transaction(function () use ($order, $proof) {
            $order = $this->lock($order);

            if ($order->status === OrderStatus::Expired) {
                throw new OrderException('Waktu pembayaran sudah habis. Jika kamu sudah transfer, hubungi admin.');
            }

            if ($order->status !== OrderStatus::Pending) {
                throw new OrderException('Pesanan ini tidak sedang menunggu pembayaran.');
            }

            if ($proof) {
                // Bukti transfer bersifat pribadi: disimpan di disk privat dan hanya dibuka lewat halaman admin.
                $order->proof_path = Storage::disk('local')->putFile('proofs', $proof);
            }

            $order->forceFill(['status' => OrderStatus::Verifying, 'claimed_paid_at' => now()])->save();

            return $order;
        });

        app(AdminNotifier::class)->send(new PaymentClaimedMail($order), 'payment');

        return $order;
    }

    /** Pelanggan membatalkan pesanan yang belum dibayar. */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);

            if ($order->status !== OrderStatus::Pending) {
                throw new OrderException('Hanya pesanan yang belum dibayar yang bisa dibatalkan.');
            }

            $this->release($order);

            $order->forceFill(['status' => OrderStatus::Cancelled])->save();

            return $order;
        });
    }

    /** Waktu bayar habis (dijalankan perintah terjadwal). Mengembalikan false bila pesanan sudah berubah. */
    public function expire(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);

            if ($order->status !== OrderStatus::Pending || $order->expires_at === null || $order->expires_at->isFuture()) {
                return false;
            }

            $this->release($order);
            $order->forceFill(['status' => OrderStatus::Expired])->save();

            return true;
        });
    }

    /** Admin menolak pembayaran (belum masuk, nominal salah, dll). Stok dan kuota voucher dilepas. */
    public function reject(Order $order, User $admin, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $admin, $reason) {
            $order = $this->lock($order);

            if (! $order->status->holdsResources()) {
                throw new OrderException('Pesanan ini tidak bisa ditolak.');
            }

            $this->release($order);
            $order->forceFill(['status' => OrderStatus::Rejected, 'verified_by' => $admin->id, 'reject_reason' => $reason])->save();

            return $order;
        });
    }

    /**
     * Admin memulihkan pesanan kedaluwarsa yang ternyata sudah dibayar: kuota voucher diambil lagi, lalu pesanan
     * masuk antrean verifikasi. Akun tetap baru diambil saat konfirmasi.
     */
    public function restore(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);

            if ($order->status !== OrderStatus::Expired) {
                throw new OrderException('Hanya pesanan kedaluwarsa yang bisa dipulihkan.');
            }

            if ($order->voucher_id) {
                Voucher::whereKey($order->voucher_id)->increment('used_count');
            }

            $order->forceFill(['status' => OrderStatus::Verifying, 'claimed_paid_at' => $order->claimed_paid_at ?? now()])->save();

            return $order;
        });
    }

    /**
     * Admin menyatakan pembayaran sah: akun bulan ke-1 diambil otomatis dari stok lalu terkirim (status Terjual, HPP
     * disalin, masa garansi dihitung), jadwal bulan berikutnya dibuat, dan kredensial dikirim ke email pesanan.
     * Stok kosong tidak menggagalkan konfirmasi: pembayaran tetap tercatat, kiriman bulan ke-1 menunggu stok
     * (dikirim `orders:deliver-due` atau tombol Kirim sekarang), dan admin langsung diberi tahu.
     */
    public function confirm(Order $order, User $admin): Order
    {
        $delivery = null;
        $account = null;

        $order = DB::transaction(function () use ($order, $admin, &$delivery, &$account) {
            $order = $this->lock($order);

            if (! $order->status->holdsResources()) {
                throw new OrderException('Pesanan ini tidak bisa dikonfirmasi.');
            }

            $delivery = $order->deliveries()->where('sequence', 1)->firstOrFail();
            $account = $this->sellAccount($order);
            $now = now();

            if ($account) {
                $this->markDelivered($order, $delivery, $account, $now);
            } else {
                $delivery->update(['due_on' => $now->toDateString(), 'stock_alerted_at' => $now]);
            }

            $order->forceFill([
                'status' => $order->months > 1 || ! $account ? OrderStatus::Paid : OrderStatus::Completed,
                'paid_at' => $now,
                'verified_by' => $admin->id,
                'reject_reason' => null,
            ])->save();

            return $order;
        });

        if ($account) {
            $this->sendDelivery($order, $delivery->fresh('account'));
        } else {
            try {
                Mail::to($order->customer_email)->queue(new PaymentReceivedMail($order));
            } catch (Throwable $e) {
                report($e);
            }
            app(AdminNotifier::class)->send(new StockShortMail([[
                'product' => $order->product_name, 'overdue' => 1, 'upcoming' => 0, 'stock' => 0,
            ]]), 'stock');
        }

        return $order;
    }

    /**
     * Mengirim kiriman yang belum terkirim (bulan ke-2 dst, atau bulan ke-1 yang menunggu stok saat konfirmasi):
     * mengambil akun tersedia, menandainya Terjual, menyalin HPP, menghitung masa garansi (per akun, dari hari kirim),
     * lalu mengirim email. Pesanan menjadi Selesai setelah akun terakhir terkirim.
     * Dipakai perintah terjadwal (hanya yang sudah jatuh tempo) dan admin ($force = kirim lebih awal).
     */
    public function deliver(OrderDelivery $delivery, bool $force = false): OrderDelivery
    {
        $order = null;

        $delivery = DB::transaction(function () use ($delivery, $force, &$order) {
            $order = $this->lock(Order::findOrFail($delivery->order_id));
            $delivery = OrderDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Paid) {
                throw new OrderException('Pesanan ini tidak sedang berjalan.');
            }

            if ($delivery->isDelivered()) {
                throw new OrderException('Akun untuk bulan ini sudah terkirim.');
            }

            if (! $force && $delivery->due_on?->gt(today())) {
                throw new OrderException('Kiriman ini belum jatuh tempo.');
            }

            $account = $this->sellAccount($order) ?? throw new OrderException('Maaf, stok produk ini sedang habis.');
            $this->markDelivered($order, $delivery, $account, now());

            if (! $order->deliveries()->whereNull('delivered_at')->exists()) {
                $order->forceFill(['status' => OrderStatus::Completed])->save();
            }

            return $delivery;
        });

        $this->sendDelivery($order, $delivery->fresh('account'));

        return $delivery;
    }

    /** Kirim ulang email kredensial untuk satu kiriman (admin). */
    public function resend(Order $order, OrderDelivery $delivery): void
    {
        if (! $order->isPaid() || ! $delivery->isDelivered()) {
            throw new OrderException('Akun belum terkirim untuk pesanan ini.');
        }

        $this->sendDelivery($order, $delivery->loadMissing('account'));
    }

    /** Email kredensial lewat antrean (dikirim setelah transaksi selesai, dicoba ulang bila SMTP gagal). */
    public function sendDelivery(Order $order, OrderDelivery $delivery, bool $replacement = false): void
    {
        try {
            Mail::to($order->customer_email)->queue(new AccountDeliveredMail($order, $delivery, $replacement));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Stok yang bisa dijual: akun Tersedia yang belum lewat masa aktif. */
    private function stock(int $productId)
    {
        return Account::available()->where('product_id', $productId)->whereDate('expires_at', '>=', today());
    }

    private function hasStock(int $productId): bool
    {
        return $this->stock($productId)->exists();
    }

    /** Mengambil satu akun tersedia (FIFO) dan langsung menandainya Terjual atas nama pesanan. Null bila stok kosong. Harus di dalam transaksi. */
    private function sellAccount(Order $order): ?Account
    {
        $account = $this->stock($order->product_id)->orderBy('id')->lockForUpdate()->first();

        $account?->forceFill(['status' => AccountStatus::Sold, 'sold_to' => $order->customer_name.' ('.$order->code.')'])->save();

        return $account;
    }

    /**
     * Mencatat akun pada kiriman. Kiriman bulan ke-1 yang terkirim (saat konfirmasi atau terlambat karena menunggu stok)
     * menjadi patokan jadwal bulan berikutnya: tiap bulan jatuh tempo sekian hari setelahnya.
     */
    private function markDelivered(Order $order, OrderDelivery $delivery, Account $account, $now): void
    {
        $delivery->update([
            'account_id' => $account->id,
            'cogs' => $account->cogs,
            'delivered_at' => $now,
            'stock_alerted_at' => null,
            'warranty_ends_on' => $order->warranty_days ? $now->copy()->addDays($order->warranty_days)->toDateString() : null,
        ]);

        if ($delivery->sequence === 1) {
            $interval = (int) config('proakses.delivery_interval_days');
            $order->deliveries()->where('sequence', '>', 1)->get()
                ->each(fn (OrderDelivery $d) => $d->update(['due_on' => $now->copy()->addDays($interval * ($d->sequence - 1))->toDateString()]));
        }
    }

    /** Melepas kuota voucher dari pesanan yang tidak jadi (akun belum pernah dikaitkan sebelum konfirmasi). */
    private function release(Order $order): void
    {
        if ($order->voucher_id) {
            Voucher::whereKey($order->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
        }
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }
}
