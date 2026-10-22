<?php

namespace App\Mail;

use App\Services\PasswordResetCodes;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetCodeMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kode reset password ProAkses');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.password-reset-code',
            with: [
                'spacedCode' => substr($this->code, 0, 3).' '.substr($this->code, 3),
                'minutes' => intdiv(PasswordResetCodes::TTL_SECONDS, 60),
            ],
        );
    }
}
