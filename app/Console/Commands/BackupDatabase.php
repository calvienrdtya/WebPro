<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsActivity;
use App\Models\Setting;
use App\Support\Activity;
use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'backup:database', description: 'Buat backup database (--auto: hanya bila backup otomatis menyala dan sudah waktunya, menu Backup)')]
class BackupDatabase extends Command
{
    use RecordsActivity;

    protected $signature = 'backup:database {--auto : Ikuti saklar dan selang hari di menu Backup}';

    public function handle(DatabaseBackup $backup): int
    {
        if ($this->option('auto') && (! Setting::backupEnabled() || ! $backup->due())) {
            $this->info('Backup otomatis belum waktunya atau dimatikan.');

            return self::SUCCESS;
        }

        try {
            $result = $backup->create($this->option('auto') ? 'otomatis' : 'manual');
        } catch (Throwable $e) {
            report($e);
            $this->error('Backup gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        Activity::note($result['name'].' ('.Number::fileSize($result['size'], 1).', '.$result['tables'].' tabel, '.$result['rows'].' baris)');
        $this->info("Backup dibuat: {$result['name']}.");

        return self::SUCCESS;
    }
}
