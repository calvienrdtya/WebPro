<?php

namespace App\Http\Controllers\Store;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\PublicName;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Halaman publik "Top Pelanggan": peringkat pembeli berdasarkan jumlah pesanan lunas. Nama disamarkan ("Rizky A."). */
class TopCustomerController extends Controller
{
    private const PERIODS = ['minggu' => 'minggu ini', 'bulan' => 'bulan ini', 'semua' => 'sepanjang waktu'];

    private const LIMIT = 30;

    private const CACHE_SECONDS = 300;

    public function __invoke(Request $request): View
    {
        $period = array_key_exists($request->query('periode'), self::PERIODS) ? $request->query('periode') : 'bulan';

        $data = Cache::remember("top-customers:{$period}", self::CACHE_SECONDS, fn () => $this->build($period));

        return view('store.top-customers', [
            'period' => $period,
            'periods' => self::PERIODS,
            'ranking' => $data['ranking'],
            'stats' => $data['stats'],
            'generatedAt' => $data['generated_at'],
        ]);
    }

    /** @return array{ranking: list<array>, stats: array, generated_at: int} */
    private function build(string $period): array
    {
        $from = match ($period) {
            'minggu' => CarbonImmutable::now()->startOfWeek(),
            'bulan' => CarbonImmutable::now()->startOfMonth(),
            default => null,
        };

        $paid = fn () => Order::query()
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->whereNotNull('orders.paid_at')
            ->where('users.is_admin', false)->whereNull('users.blocked_at');

        $rows = $paid()
            ->when($from, fn ($q) => $q->where('orders.paid_at', '>=', $from))
            ->groupBy('orders.user_id', 'users.name')
            ->selectRaw('orders.user_id, users.name, count(*) as orders, sum(orders.total) as spend')
            ->orderByDesc('orders')->orderByDesc('spend')->orderBy('orders.user_id')
            ->limit(self::LIMIT)
            ->get();

        // Level mengikuti jumlah pesanan sepanjang waktu, bukan periode yang sedang dilihat.
        $lifetime = $paid()->whereIn('orders.user_id', $rows->pluck('user_id'))->groupBy('orders.user_id')
            ->selectRaw('orders.user_id, count(*) as total')->pluck('total', 'user_id');

        $ranking = $rows->map(fn ($r) => [
            'name' => PublicName::of($r->name),
            'orders' => (int) $r->orders,
            'spend' => (int) $r->spend,
            'level' => match (true) {
                ($lifetime[$r->user_id] ?? 0) >= 100 => 'legend',
                ($lifetime[$r->user_id] ?? 0) >= 60 => 'elite',
                ($lifetime[$r->user_id] ?? 0) >= 30 => 'loyal',
                default => 'member',
            },
        ])->values()->all();

        return [
            'ranking' => $ranking,
            'stats' => [
                'customers' => (int) $paid()->distinct()->count('orders.user_id'),
                'orders' => (int) $paid()->count(),
            ],
            'generated_at' => now()->getTimestamp(),
        ];
    }
}
