<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsActivity;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Mail\StockShortMail;
use App\Models\Account;
use App\Models\OrderDelivery;
use App\Services\AdminNotifier;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'orders:deliver-due', description: 'Kirim akun yang sudah jatuh tempo (bulan ke-2 dst, atau bulan ke-1 yang menunggu stok) dan beri tahu admin bila stok kurang')]
class DeliverDueOrders extends Command
{
    use RecordsActivity;

    /** Kebutuhan berapa hari ke depan yang dibandingkan dengan stok untuk peringatan dini. */
    private const FORECAST_DAYS = 7;

    public function handle(OrderService $orders, AdminNotifier $notifier): int
    {
        $delivered = 0;
        $short = collect();

        OrderDelivery::query()
            ->with('order')
            ->whereNull('delivered_at')
            ->whereDate('due_on', '<=', today())
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Paid->value))
            ->orderBy('due_on')->orderBy('id')
            ->get()
            ->each(function (OrderDelivery $delivery) use ($orders, &$delivered, $short) {
                try {
                    $orders->deliver($delivery);
                    $delivered++;
                } catch (OrderException) {
                    $short->push($delivery); // stok produk ini kosong; dicoba lagi pada pengecekan berikutnya
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $alerted = $this->alertAdmin($short, $notifier);

        $this->info("{$delivered} akun terkirim, {$short->count()} tertunda karena stok kurang".($alerted ? ', admin diberi tahu.' : '.'));

        return self::SUCCESS;
    }

    /** Peringatan stok: kiriman yang tertunda (maks. sekali per 24 jam per kiriman) dan kekurangan stok untuk jatuh tempo 7 hari ke depan (sekali per hari). */
    private function alertAdmin($short, AdminNotifier $notifier): bool
    {
        $fresh = $short->filter(fn (OrderDelivery $d) => $d->stock_alerted_at === null || $d->stock_alerted_at->lt(now()->subDay()));

        $upcoming = OrderDelivery::query()
            ->join('orders', 'orders.id', '=', 'order_deliveries.order_id')
            ->whereNull('order_deliveries.delivered_at')->where('order_deliveries.sequence', '>', 1)
            ->where('orders.status', OrderStatus::Paid->value)
            ->whereDate('order_deliveries.due_on', '>', today())
            ->whereDate('order_deliveries.due_on', '<=', today()->addDays(self::FORECAST_DAYS))
            ->selectRaw('orders.product_id, orders.product_name, count(*) as total')
            ->groupBy('orders.product_id', 'orders.product_name')
            ->get()->keyBy('product_id');

        $overdue = $short->groupBy(fn (OrderDelivery $d) => $d->order->product_id);
        $stock = fn (int $productId) => Account::available()->where('product_id', $productId)->whereDate('expires_at', '>=', today())->count();

        $lines = [];
        foreach ($overdue->keys()->merge($upcoming->keys())->unique() as $productId) {
            $late = $overdue->get($productId, collect())->count();
            $soon = (int) ($upcoming[$productId]->total ?? 0);
            $available = $stock((int) $productId);

            if ($late > 0 || $soon > $available) {
                $lines[] = [
                    'product' => $overdue->get($productId)?->first()->order->product_name ?? $upcoming[$productId]->product_name,
                    'overdue' => $late,
                    'upcoming' => $soon,
                    'stock' => $available,
                ];
            }
        }

        if ($lines === []) {
            return false;
        }

        // Ada kiriman tertunda yang belum diberi tahu: kirim. Tertunda tetapi sudah diberi tahu < 24 jam lalu: diam.
        // Tidak ada yang tertunda (hanya peringatan dini kebutuhan ke depan): cukup sekali per hari.
        $send = $fresh->isNotEmpty() || ($short->isEmpty() && Cache::add('stock-forecast-alert:'.today()->toDateString(), 1, now()->addDay()));

        if (! $send) {
            return false;
        }

        $fresh->each(fn (OrderDelivery $d) => $d->forceFill(['stock_alerted_at' => now()])->save());

        return $notifier->send(new StockShortMail($lines), 'stock');
    }
}
