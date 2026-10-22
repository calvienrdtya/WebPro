<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Peringatan stok kurang untuk kiriman bulanan: yang sudah tertunda dan perkiraan kebutuhan beberapa hari ke depan. */
class StockShortMail extends QueuedMail
{
    /**
     * @param  list<array{product: string, overdue: int, upcoming: int, stock: int}>  $lines
     */
    public function __construct(public array $lines) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Stok akun kurang untuk kiriman bulanan');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.stock-short', with: ['lines' => $this->lines, 'url' => url('/admin/pesanan?status=overdue')]);
    }
}
