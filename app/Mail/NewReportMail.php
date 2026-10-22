<?php

namespace App\Mail;

use App\Models\CustomerReport;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

class NewReportMail extends QueuedMail
{
    public function __construct(public CustomerReport $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Laporan baru: {$this->report->subject}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-report',
            with: [
                'customer' => $this->report->user->name,
                'email' => $this->report->user->email,
                'category' => $this->report->category->label(),
                'subject' => $this->report->subject,
                'excerpt' => Str::limit($this->report->body, 300),
                'url' => url('/admin/laporan'),
            ],
        );
    }
}
