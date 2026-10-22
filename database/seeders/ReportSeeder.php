<?php

namespace Database\Seeders;

use App\Enums\ReportCategory;
use App\Models\CustomerReport;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Laporan contoh untuk user contoh. Dilewati bila user itu sudah punya laporan. */
class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'user@proakses.test')->first();

        if (! $user || CustomerReport::where('user_id', $user->id)->exists()) {
            return;
        }

        foreach ([
            [ReportCategory::Account, 'Akun Netflix tidak bisa login', 'Akun yang saya terima muncul pesan password salah sejak tadi pagi.'],
            [ReportCategory::Account, 'Akun Netflix tidak bisa login', 'Sudah saya coba lagi malam ini, tetap tidak bisa masuk.'],
            [ReportCategory::Payment, 'Sudah transfer belum diproses', 'Saya sudah bayar QRIS pukul 10.15 tapi status pesanan belum berubah.'],
        ] as [$category, $subject, $body]) {
            CustomerReport::create(['user_id' => $user->id, 'category' => $category, 'subject' => $subject, 'body' => $body]);
        }
    }
}
