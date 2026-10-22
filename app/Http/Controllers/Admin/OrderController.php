<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Services\OrderService;
use App\Support\OrderPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    /** Tab -> status yang ditampilkan. "queue" = perlu tindakan admin. */
    private const TABS = [
        'queue' => [OrderStatus::Verifying],
        'unpaid' => [OrderStatus::Pending],
        'paid' => [OrderStatus::Paid, OrderStatus::Completed],
        'failed' => [OrderStatus::Expired, OrderStatus::Rejected, OrderStatus::Cancelled],
    ];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $tab = array_key_exists($request->query('status'), self::TABS) || in_array($request->query('status'), ['all', 'overdue'], true) ? $request->query('status') : 'all';

        $orders = Order::query()
            ->with(OrderPresenter::RELATIONS)
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('code', 'like', "%{$q}%")
                ->orWhere('customer_name', 'like', "%{$q}%")
                ->orWhere('customer_email', 'like', "%{$q}%")
                ->orWhere('customer_phone', 'like', "%{$q}%")
                ->orWhere('product_name', 'like', "%{$q}%")))
            ->when($tab === 'overdue', fn (Builder $query) => $query->whereIn('id', $this->overdueOrderIds()))
            ->when(! in_array($tab, ['all', 'overdue'], true), fn (Builder $query) => $query->whereIn('status', $this->values(self::TABS[$tab])))
            // Antrean verifikasi: yang paling lama menunggu di atas.
            ->when($tab === 'queue', fn (Builder $query) => $query->orderBy('claimed_paid_at'), fn (Builder $query) => $query->latest('id'))
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $o) => OrderPresenter::row($o));

        $counts = Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tabCounts = collect(self::TABS)->map(fn (array $statuses) => (int) collect($statuses)->sum(fn (OrderStatus $s) => $counts[$s->value] ?? 0))->all();
        $tabCounts['all'] = (int) $counts->sum();

        $month = now()->startOfMonth();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => ['q' => $q, 'status' => $tab],
            'counts' => $tabCounts,
            'stats' => [
                'to_verify' => $tabCounts['queue'],
                'unpaid' => $tabCounts['unpaid'],
                'today' => Order::whereIn('status', $this->values([OrderStatus::Paid, OrderStatus::Completed]))->whereDate('paid_at', today())->count(),
                'overdue' => $this->overdueOrderIds()->count(),
                'month_revenue' => (int) Order::whereIn('status', $this->values([OrderStatus::Paid, OrderStatus::Completed]))->where('paid_at', '>=', $month)->sum('total'),
            ],
        ]);
    }

    public function confirm(Request $request, OrderService $orders, Order $order): RedirectResponse
    {
        try {
            $order = $orders->confirm($order, $request->user());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Stok kosong: pembayaran tetap tercatat, akun menyusul begitu stok ditambah.
        return $order->deliveries()->where('sequence', 1)->whereNotNull('delivered_at')->exists()
            ? back()->with('success', "Pesanan {$order->code} dikonfirmasi dan akun dikirim ke {$order->customer_email}.")
            : back()->with('warning', "Pesanan {$order->code} dikonfirmasi, tetapi stok {$order->product_name} habis. Akun dikirim otomatis begitu stok ditambah.");
    }

    /** Admin mengirim akun bulan berikutnya sekarang (mis. setelah stok ditambah), tanpa menunggu jadwal. */
    public function deliver(OrderService $orders, Order $order, OrderDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->order_id === $order->id, 404);

        return $this->run(fn () => $orders->deliver($delivery, force: true), "Akun bulan ke-{$delivery->sequence} pesanan {$order->code} dikirim ke {$order->customer_email}.");
    }

    public function reject(Request $request, OrderService $orders, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']], [], ['reason' => 'Alasan']);

        return $this->run(fn () => $orders->reject($order, $request->user(), $data['reason'] ?? null), "Pesanan {$order->code} ditolak.");
    }

    public function restore(OrderService $orders, Order $order): RedirectResponse
    {
        return $this->run(fn () => $orders->restore($order), "Pesanan {$order->code} dipulihkan dan masuk antrean verifikasi.");
    }

    public function resend(OrderService $orders, Order $order, OrderDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->order_id === $order->id, 404);

        return $this->run(fn () => $orders->resend($order, $delivery), "Email akun dikirim ulang ke {$order->customer_email}.");
    }

    /** Catatan internal admin (tidak terlihat pelanggan). */
    public function note(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']], [], ['note' => 'Catatan']);
        $order->update(['admin_note' => $data['note'] ?? null]);

        return back()->with('success', 'Catatan disimpan.');
    }

    /** Bukti transfer disimpan di disk privat; hanya admin yang bisa membukanya lewat sini. */
    public function proof(Order $order): StreamedResponse
    {
        abort_unless($order->proof_path && Storage::disk('local')->exists($order->proof_path), 404);

        return Storage::disk('local')->response($order->proof_path);
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }

    /** Pesanan berjalan yang punya kiriman bulanan jatuh tempo tetapi belum terkirim (biasanya karena stok kurang). */
    private function overdueOrderIds()
    {
        return OrderDelivery::query()
            ->whereNull('delivered_at')->whereDate('due_on', '<=', today())
            ->whereHas('order', fn (Builder $q) => $q->where('status', OrderStatus::Paid->value))
            ->pluck('order_id')->unique()->values();
    }

    /** @param  list<OrderStatus>  $statuses @return list<string> */
    private function values(array $statuses): array
    {
        return array_map(fn (OrderStatus $s) => $s->value, $statuses);
    }
}
