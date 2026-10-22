<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentClaimedMail extends QueuedMail
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pembayaran menunggu verifikasi: {$this->order->code}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.payment-claimed', with: [
            'code' => $this->order->code,
            'customer' => $this->order->customer_name,
            'email' => $this->order->customer_email,
            'product' => $this->order->product_name.($this->order->variant_title ? ' | '.$this->order->variant_title : ''),
            'total' => 'Rp '.number_format($this->order->total, 0, ',', '.'),
            'url' => url('/admin/pesanan?status=queue'),
        ]);
    }
}
