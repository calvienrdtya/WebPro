<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Angka keuangan dashboard. Pesanan dihitung pada tanggal dibayar (paid_at) dan hanya yang lunas (Paid, Completed).
 *
 * Revenue = total yang dibayar pelanggan. HPP = HPP akun yang sudah terkirim ditambah estimasi untuk bulan yang belum terkirim
 * (rata-rata HPP stok tersedia produk itu, cadangan: rata-rata HPP kiriman produk itu). Biaya klaim belum dihitung.
 *   Profit      = harga produk setelah voucher - HPP     (margin murni produk)
 *   Biaya admin = jumlah biaya layanan
 *   Garansi     = jumlah harga garansi
 *   Total laba  = Profit + Biaya admin + Garansi = Revenue - HPP
 */
class DashboardStats
{
    public const PERIODS = ['bulan-ini', 'bulan-lalu', '7-hari', '30-hari', 'tahun-ini', 'semua'];

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} awal dan akhir periode (null = tanpa batas) */
    public static function range(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'bulan-ini' => [$now->startOfMonth(), $now->endOfDay()],
            'bulan-lalu' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            '7-hari' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '30-hari' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'tahun-ini' => [$now->startOfYear(), $now->endOfDay()],
            default => [null, null],
        };
    }

    /** Periode pembanding: rentang yang sama panjangnya tepat sebelum periode ini; null untuk "semua". */
    public static function previous(?CarbonImmutable $from, ?CarbonImmutable $to): ?array
    {
        if (! $from || ! $to) {
            return null;
        }

        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;

        return [$from->subDays($days), $from->subSecond()];
    }

    private function paidOrders(?CarbonImmutable $from, ?CarbonImmutable $to): Builder
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->whereNotNull('paid_at')
            ->when($from, fn (Builder $q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('paid_at', '<=', $to));
    }

    /** @return array<string, int|float> */
    public function finance(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $orders = $this->paidOrders($from, $to);

        $sums = (clone $orders)->selectRaw('count(*) as orders, coalesce(sum(total), 0) as revenue, coalesce(sum(product_price), 0) as product, coalesce(sum(warranty_price), 0) as warranty, coalesce(sum(service_fee), 0) as fee, coalesce(sum(voucher_discount), 0) as voucher')->first();

        $ids = (clone $orders)->select('id');
        $hppActual = (int) OrderDelivery::whereIn('order_id', $ids)->whereNotNull('delivered_at')->sum('cogs');
        $hppEstimate = $this->estimatedHpp($ids);

        $hpp = $hppActual + $hppEstimate;
        $revenue = (int) $sums->revenue;
        $profit = (int) $sums->product - (int) $sums->voucher - $hpp;

        return [
            'orders' => (int) $sums->orders,
            'revenue' => $revenue,
            'hpp' => $hpp,
            'hpp_estimate' => $hppEstimate,
            'profit' => $profit,
            'fee' => (int) $sums->fee,
            'warranty' => (int) $sums->warranty,
            'total_profit' => $revenue - $hpp,
        ];
    }

    /** Estimasi HPP kiriman yang belum terkirim: jumlah kiriman per produk dikali rata-rata HPP produk itu. */
    private function estimatedHpp($orderIds): int
    {
        $pending = OrderDelivery::query()
            ->join('orders', 'orders.id', '=', 'order_deliveries.order_id')
            ->whereIn('order_deliveries.order_id', $orderIds)
            ->whereNull('order_deliveries.delivered_at')
            ->groupBy('orders.product_id')
            ->selectRaw('orders.product_id, count(*) as total')
            ->pluck('total', 'product_id');

        if ($pending->isEmpty()) {
            return 0;
        }

        $stockAvg = Account::query()->where('status', AccountStatus::Available->value)->whereIn('product_id', $pending->keys())
            ->whereNotNull('cogs')->groupBy('product_id')->selectRaw('product_id, avg(cogs) as avg_cogs')->pluck('avg_cogs', 'product_id');
        $historyAvg = OrderDelivery::query()->join('orders', 'orders.id', '=', 'order_deliveries.order_id')
            ->whereIn('orders.product_id', $pending->keys())->whereNotNull('order_deliveries.cogs')
            ->groupBy('orders.product_id')->selectRaw('orders.product_id, avg(order_deliveries.cogs) as avg_cogs')->pluck('avg_cogs', 'product_id');

        return (int) round($pending->sum(fn ($count, $productId) => $count * (float) ($stockAvg[$productId] ?? $historyAvg[$productId] ?? 0)));
    }

    /**
     * Revenue dan total laba per bulan untuk 12 bulan terakhir (bulan ini paling kanan).
     *
     * @return list<array{month: string, label: string, revenue: int, profit: int}>
     */
    public function monthly(int $months = 12): array
    {
        $now = CarbonImmutable::now()->startOfMonth();

        return collect(range($months - 1, 0))->map(function (int $ago) use ($now) {
            $start = $now->subMonthsNoOverflow($ago);
            $f = $this->finance($start, $start->endOfMonth());

            return ['month' => $start->format('Y-m'), 'label' => $start->translatedFormat('M'), 'revenue' => $f['revenue'], 'profit' => $f['total_profit']];
        })->all();
    }

    /**
     * Pesanan dibuat dan pesanan dibayar per hari untuk 30 hari terakhir.
     *
     * @return list<array{date: string, label: string, created: int, paid: int}>
     */
    public function daily(int $days = 30): array
    {
        $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $created = Order::where('created_at', '>=', $from)->get(['created_at'])->groupBy(fn (Order $o) => $o->created_at->toDateString())->map->count();
        $paid = $this->paidOrders($from, null)->get(['paid_at'])->groupBy(fn (Order $o) => $o->paid_at->toDateString())->map->count();

        return collect(range(0, $days - 1))->map(function (int $i) use ($from, $created, $paid) {
            $day = $from->addDays($i);
            $key = $day->toDateString();

            return ['date' => $key, 'label' => $day->translatedFormat('j M'), 'created' => (int) ($created[$key] ?? 0), 'paid' => (int) ($paid[$key] ?? 0)];
        })->all();
    }

    /**
     * Produk terlaris pada periode: jumlah pesanan lunas dan revenue.
     *
     * @return list<array{name: string, orders: int, revenue: int}>
     */
    public function topProducts(?CarbonImmutable $from, ?CarbonImmutable $to, int $limit = 6): array
    {
        return $this->paidOrders($from, $to)
            ->groupBy('product_id', 'product_name')
            ->selectRaw('product_name, count(*) as orders, sum(total) as revenue')
            ->orderByDesc('orders')->orderByDesc('revenue')->limit($limit)
            ->get()->map(fn ($r) => ['name' => $r->product_name, 'orders' => (int) $r->orders, 'revenue' => (int) $r->revenue])->all();
    }

    /** Jumlah pesanan per status (seluruh waktu), untuk ringkasan analitik. */
    public function byStatus(): array
    {
        $counts = Order::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return collect(OrderStatus::cases())->map(fn (OrderStatus $s) => ['status' => $s->value, 'label' => $s->label(), 'total' => (int) ($counts[$s->value] ?? 0)])->all();
    }
}
