<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/** Pengingat ke pelanggan: akun terakhir pesanan segera habis, ajak memperpanjang dengan pesanan baru. */
class RenewalReminderMail extends QueuedMail
{
    public function __construct(public Order $order, public Carbon $expiresOn) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Akun {$this->order->product_name} kamu habis ".$this->expiresOn->translatedFormat('j F Y'));
    }

    public function content(): Content
    {
        $product = $this->order->product;

        return new Content(
            markdown: 'mail.renewal-reminder',
            with: [
                'name' => $this->order->customer_name,
                'product' => $this->order->product_name,
                'code' => $this->order->code,
                'expires' => $this->expiresOn->translatedFormat('l, j F Y'),
                'daysLeft' => (int) today()->diffInDays($this->expiresOn),
                'url' => $product && $product->is_active && ! $product->trashed() ? route('products.show', $product->slug) : route('products.index'),
            ],
        );
    }
}
