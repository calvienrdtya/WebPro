<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Mail\QueuedMail;
use App\Mail\StockShortMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailQueueTest extends TestCase
{
    use RefreshDatabase;

    private function mail(): StockShortMail
    {
        return new StockShortMail([['product' => 'Netflix', 'overdue' => 1, 'upcoming' => 0, 'stock' => 0]]);
    }

    public function test_business_emails_go_through_the_queue_but_the_reset_code_does_not(): void
    {
        foreach (File::files(app_path('Mail')) as $file) {
            $class = 'App\\Mail\\'.$file->getFilenameWithoutExtension();
            if ($class === QueuedMail::class) {
                continue;
            }
            $this->assertSame($class !== PasswordResetCodeMail::class, is_subclass_of($class, ShouldQueue::class), $class);
        }
    }

    public function test_queued_email_carries_retry_settings_and_waits_for_the_transaction(): void
    {
        config(['queue.default' => 'database', 'proakses.mail_queue' => ['tries' => 4, 'backoff' => [30, 120]]]);

        // Transaksi dibatalkan: email tidak boleh ikut terkirim.
        DB::beginTransaction();
        Mail::to('admin@uji.test')->queue($this->mail());
        DB::rollBack();
        $this->assertSame(0, DB::table('jobs')->count());

        Mail::to('admin@uji.test')->queue($this->mail());
        $payload = json_decode(DB::table('jobs')->value('payload'), true);
        $this->assertSame(4, $payload['maxTries']);
        $this->assertSame('30,120', $payload['backoff']);
    }
}
