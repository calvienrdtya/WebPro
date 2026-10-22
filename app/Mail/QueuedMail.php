<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email yang dikirim lewat antrean database (dijalankan cron `queue:work` tiap menit, lihat routes/console.php).
 * Masuk antrean setelah transaksi database selesai; bila SMTP gagal dicoba ulang sesuai config proakses.mail_queue,
 * lalu masuk failed_jobs (terlihat di Panduan Deploy). Data model dibaca ulang saat email benar-benar dikirim.
 */
abstract class QueuedMail extends Mailable implements ShouldQueueAfterCommit
{
    use SerializesModels;

    /** Diisi dari config saat masuk antrean. */
    public $tries;

    public function queue(Queue $queue)
    {
        $this->tries = (int) config('proakses.mail_queue.tries');

        return parent::queue($queue);
    }

    /** Jeda sebelum mencoba ulang (detik), per percobaan. */
    public function backoff(): array
    {
        return config('proakses.mail_queue.backoff');
    }
}
