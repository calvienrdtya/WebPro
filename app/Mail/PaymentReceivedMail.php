<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Pembayaran sudah diverifikasi tetapi stok sedang kosong: akun menyusul lewat AccountDeliveredMail. */
class PaymentReceivedMail extends QueuedMail
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pembayaran {$this->order->code} diterima, akun segera dikirim");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-received',
            with: [
                'name' => $this->order->customer_name,
                'product' => $this->order->product_name,
                'code' => $this->order->code,
                'email' => $this->order->customer_email,
                'url' => route('orders.show', $this->order),
            ],
        );
    }
}
