<?php

namespace App\Http\Controllers\Store;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pembeli menulis ulasan untuk pesanannya yang sudah lunas. Satu ulasan per pesanan; masuk status menunggu persetujuan admin. */
class ReviewController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $back = fn () => redirect()->route('profil', ['tab' => 'pesanan']);

        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Completed], true)) {
            return $back()->with('error', 'Ulasan hanya bisa ditulis untuk pesanan yang sudah lunas.');
        }

        if (Review::where('order_id', $order->id)->exists()) {
            return $back()->with('info', 'Kamu sudah menulis ulasan untuk pesanan ini.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'rating.required' => 'Pilih jumlah bintang dulu.',
            'rating.between' => 'Pilih jumlah bintang dulu.',
            'body.min' => 'Ulasan minimal 10 karakter.',
        ], ['rating' => 'Bintang', 'body' => 'Ulasan']);

        // `status` sengaja tidak mass-assignable: ulasan baru selalu menunggu persetujuan (bawaan database).
        Review::create([
            'product_id' => $order->product_id,
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'reviewer_name' => $request->user()->name,
            'rating' => $data['rating'],
            'body' => trim($data['body']),
        ]);

        return $back()->with('success', 'Terima kasih! Ulasan kamu akan tampil setelah disetujui admin.');
    }
}
