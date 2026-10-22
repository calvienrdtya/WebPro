<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsActivity;
use App\Enums\AccountStatus;
use App\Models\Account;
use App\Support\Activity;
use Symfony\Component\Console\Attribute\AsCommand;
use Illuminate\Console\Command;

#[AsCommand(name: 'accounts:expire', description: 'Ubah akun tersedia yang masa aktifnya sudah lewat menjadi kedaluwarsa')]
class ExpireAccounts extends Command
{
    use RecordsActivity;

    public function handle(): int
    {
        // Hanya stok yang belum terjual. Akun terjual/bermasalah ditangani manual.
        $count = Account::query()
            ->where('status', AccountStatus::Available)
            ->whereDate('expires_at', '<', today())
            ->update(['status' => AccountStatus::Expired, 'updated_at' => now()]);

        if ($count > 0) {
            Activity::note("{$count} akun ditandai kedaluwarsa");
        }
        $this->info("{$count} akun diubah menjadi kedaluwarsa.");

        return self::SUCCESS;
    }
}
