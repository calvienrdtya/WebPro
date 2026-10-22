<?php

namespace App\Http\Controllers\Store;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->with('product:id,slug,image')->latest('id')->paginate(10);

        return view('store.orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, OrderService $orders, Order $order): View
    {
        $this->authorizeOwner($request, $order);
        $order = $this->settle($orders, $order);

        $order->load(['deliveries.account', 'product']);

        return view('store.orders.show', [
            'order' => $order,
            'qris' => Setting::qrisUrl(),
        ]);
    }

    /** Dipoll halaman bayar/menunggu: kapan pun status berubah, halaman memuat ulang dirinya. */
    public function status(Request $request, OrderService $orders, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        $order = $this->settle($orders, $order);

        return response()->json(['status' => $order->status->value]);
    }

    public function claim(Request $request, OrderService $orders, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);
        $order = $this->settle($orders, $order);
        $data = $request->validate([
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [
            'proof.mimes' => 'Bukti transfer harus berupa gambar JPG, PNG, atau WebP.',
            'proof.max' => 'Ukuran bukti transfer maksimal 3 MB.',
            'proof.uploaded' => 'Bukti transfer gagal diunggah. Coba lagi dengan gambar yang lebih kecil.',
        ], ['proof' => 'Bukti transfer']);

        try {
            $orders->claimPaid($order, $data['proof'] ?? null);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('success', 'Terima kasih! Pembayaran kamu sedang diverifikasi admin.');
    }

    public function cancel(Request $request, OrderService $orders, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        try {
            $orders->cancel($order);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.index')->with('success', 'Pesanan dibatalkan.');
    }

    /** Pesanan yang batas bayarnya lewat langsung dikedaluwarsakan, tanpa menunggu perintah terjadwal. */
    private function settle(OrderService $orders, Order $order): Order
    {
        return $orders->expire($order) ? $order->refresh() : $order;
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
