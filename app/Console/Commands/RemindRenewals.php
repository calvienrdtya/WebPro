<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsActivity;
use App\Enums\OrderStatus;
use App\Mail\RenewalReminderMail;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'orders:remind-renewal', description: 'Email pengingat perpanjangan ke pelanggan yang akun terakhir pesanannya segera habis')]
class RemindRenewals extends Command
{
    use RecordsActivity;

    public function handle(): int
    {
        if (! Setting::renewalEnabled()) {
            $this->info('Pengingat perpanjangan dimatikan di Pengaturan.');

            return self::SUCCESS;
        }

        $days = Setting::renewalDays();
        $sent = 0;

        // Pesanan selesai yang belum diingatkan dan akun kiriman terakhirnya habis dalam rentang hari ini s/d N hari lagi
        // (rentang, bukan tanggal tepat, supaya jadwal yang sempat terlewat tetap terkirim).
        Order::query()
            ->with(['product' => fn ($q) => $q->withTrashed(), 'deliveries.account'])
            ->where('status', OrderStatus::Completed->value)
            ->whereNull('renewal_reminded_at')
            ->whereHas('deliveries', fn ($q) => $q->whereColumn('order_deliveries.sequence', 'orders.months')
                ->whereNotNull('delivered_at')
                ->whereHas('account', fn ($a) => $a->whereDate('expires_at', '>=', today())->whereDate('expires_at', '<=', today()->addDays($days))))
            ->orderBy('id')
            ->get()
            ->each(function (Order $order) use (&$sent) {
                // Sudah memperpanjang (ada pesanan produk yang sama setelah pesanan ini): tidak perlu diingatkan.
                $renewed = Order::query()->where('user_id', $order->user_id)->where('product_id', $order->product_id)
                    ->where('id', '>', $order->id)
                    ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Verifying->value, OrderStatus::Paid->value, OrderStatus::Completed->value])
                    ->exists();

                if ($renewed) {
                    $order->forceFill(['renewal_reminded_at' => now()])->save();

                    return;
                }

                $expires = $order->deliveries->firstWhere('sequence', $order->months)->account->expires_at;

                try {
                    // Masuk antrean; SMTP gagal dicoba ulang oleh antrean (config proakses.mail_queue).
                    Mail::to($order->customer_email)->queue(new RenewalReminderMail($order, $expires));
                    $order->forceFill(['renewal_reminded_at' => now()])->save();
                    $sent++;
                } catch (Throwable $e) {
                    report($e); // gagal masuk antrean: dicoba lagi pada jadwal berikutnya
                }
            });

        $this->info("{$sent} pengingat perpanjangan masuk antrean (akun habis dalam {$days} hari).");

        return self::SUCCESS;
    }
}
