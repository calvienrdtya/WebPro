<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\Activity;
use App\Support\SystemLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'logs:prune', description: 'Hapus Log Sistem dan Log Aktivitas yang melewati masa simpan (menu Pengaturan, Masa simpan log)')]
class PruneLogs extends Command
{
    public function handle(SystemLog $log): int
    {
        $systemDays = Setting::systemLogDays();
        $files = count($log->prune($systemDays));

        // Dihapus bertahap agar tabel besar tidak mengunci database terlalu lama.
        $activityDays = Setting::activityLogDays();
        $rows = 0;
        $cutoff = now()->subDays($activityDays);
        do {
            $deleted = ActivityLog::where('created_at', '<', $cutoff)->orderBy('id')->limit(1000)->delete();
            $rows += $deleted;
        } while ($deleted > 0);

        if ($files + $rows > 0) {
            Activity::record('logs:prune', 'system', "Menghapus log lama: {$files} file log sistem (lebih dari {$systemDays} hari) dan {$rows} catatan aktivitas (lebih dari {$activityDays} hari)", [
                'actor_type' => 'system',
            ]);
        }

        $this->info("{$files} file log sistem dan {$rows} catatan aktivitas dihapus.");

        return self::SUCCESS;
    }
}
