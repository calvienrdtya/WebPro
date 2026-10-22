<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsActivity;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orders:expire', description: 'Kedaluwarsakan pesanan yang melewati batas bayar dan lepaskan stok serta kuota voucher')]
class ExpireOrders extends Command
{
    use RecordsActivity;

    public function handle(OrderService $orders): int
    {
        $count = 0;

        Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->each(function (Order $order) use ($orders, &$count) {
                $count += $orders->expire($order) ? 1 : 0;
            });

        $this->info("{$count} pesanan dikedaluwarsakan.");

        return self::SUCCESS;
    }
}
