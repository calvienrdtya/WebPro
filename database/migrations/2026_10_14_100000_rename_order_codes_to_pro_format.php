<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Format kode pesanan berubah dari ORD-yymmdd-XXXXX menjadi PROyymmddXXXXX; kode lama ikut diubah. */
    public function up(): void
    {
        Order::query()->where('code', 'like', 'ORD-%')->each(function (Order $order) {
            $order->forceFill(['code' => 'PRO'.str_replace('-', '', substr($order->code, 4))])->save();
        });
    }

    public function down(): void
    {
        Order::query()->where('code', 'like', 'PRO%')->each(function (Order $order) {
            $body = substr($order->code, 3);
            $order->forceFill(['code' => 'ORD-'.substr($body, 0, 6).'-'.substr($body, 6)])->save();
        });
    }
};
