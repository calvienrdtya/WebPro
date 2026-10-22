<?php

namespace App\Http\Controllers\Store;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\PlaceOrderRequest;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    /** Tamu yang menekan Beli diarahkan login lewat alamat ini, lalu kembali ke halaman produk. */
    public function resume(string $slug): RedirectResponse
    {
        return redirect()->route('products.show', $slug);
    }

    public function store(PlaceOrderRequest $request, OrderService $orders, string $slug): RedirectResponse
    {
        $product = Product::active()->where('slug', $slug)->firstOrFail();
        $data = $request->validated();

        try {
            $order = $orders->place($request->user(), $product, [
                'option' => (int) $data['option'],
                'warranty_id' => isset($data['garansi']) ? (int) $data['garansi'] : null,
                'voucher' => $data['voucher'] ?? null,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['wa'],
            ]);
        } catch (OrderException $e) {
            return redirect()->route('products.show', $slug)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order);
    }
}
