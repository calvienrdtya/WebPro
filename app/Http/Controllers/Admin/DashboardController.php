<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\User;
use App\Services\DashboardStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $period = in_array($request->query('periode'), DashboardStats::PERIODS, true) ? $request->query('periode') : 'bulan-ini';
        [$from, $to] = DashboardStats::range($period);
        $previous = DashboardStats::previous($from, $to);

        $users = User::nonAdmin();

        return Inertia::render('Dashboard', [
            'period' => $period,
            'finance' => $stats->finance($from, $to),
            // Perubahan dibanding periode sebelumnya (persen); null bila periode sebelumnya belum punya data atau periode "semua".
            'previous' => $previous ? $stats->finance(...$previous) : null,
            'chart' => $stats->chart($from, $to),
            'daily' => $stats->daily(),
            'top_products' => $stats->topProducts($from, $to),
            'by_status' => $stats->byStatus(),
            'queue' => [
                'verifying' => Order::where('status', OrderStatus::Verifying->value)->count(),
                'unpaid' => Order::where('status', OrderStatus::Pending->value)->count(),
                'overdue' => OrderDelivery::whereNull('delivered_at')->whereDate('due_on', '<=', today())
                    ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Paid->value))->distinct()->count('order_id'),
            ],
            'users' => [
                'total' => (clone $users)->count(),
                'this_month' => (clone $users)->where('created_at', '>=', now()->startOfMonth())->count(),
                'latest' => (clone $users)->latest()->limit(5)->get()->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'joined' => $user->created_at->diffForHumans(),
                ])->all(),
            ],
        ]);
    }
}
