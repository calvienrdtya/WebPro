<?php

namespace App\Console\Concerns;

use App\Support\Activity;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Perintah terjadwal yang dicatat di Log Aktivitas (pelaku: Sistem), hanya bila mengubah data.
 * Kalimatnya di config activity.commands. Berlaku saat dijalankan cron, CLI, maupun Artisan::call.
 */
trait RecordsActivity
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        Activity::start();
        try {
            return parent::run($input, $output);
        } finally {
            Activity::finishCommand((string) $this->getName(), Activity::stop());
        }
    }
}
