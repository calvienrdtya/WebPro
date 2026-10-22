<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderDelivery;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountDeliveredMail extends QueuedMail
{
    public function __construct(public Order $order, public OrderDelivery $delivery, public bool $replacement = false) {}

    public function envelope(): Envelope
    {
        $month = $this->order->months > 1 ? " (bulan ke-{$this->delivery->sequence} dari {$this->order->months})" : '';

        return new Envelope(subject: $this->replacement
            ? "Akun pengganti {$this->order->product_name} kamu sudah siap{$month}"
            : "Akun {$this->order->product_name} kamu sudah siap{$month}");
    }

    public function content(): Content
    {
        $account = $this->delivery->account;

        return new Content(
            markdown: 'mail.account-delivered',
            with: [
                'name' => $this->order->customer_name,
                'product' => $this->order->product_name,
                'code' => $this->order->code,
                'sequence' => $this->delivery->sequence,
                'replacement' => $this->replacement,
                'months' => $this->order->months,
                'email' => $account?->email,
                'password' => $account?->password,
                'profile' => $account?->profile,
                'pin' => $account?->pin,
                'warrantyEnds' => $this->delivery->warranty_ends_on?->translatedFormat('j F Y'),
                'url' => route('orders.show', $this->order),
            ],
        );
    }
}
