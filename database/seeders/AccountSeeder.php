<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Product;
use Illuminate\Database\Seeder;

/** Akun contoh untuk pengembangan lokal (data palsu, tidak dijalankan di production). */
class AccountSeeder extends Seeder
{
    /** HPP contoh per profil. */
    private const COGS = [
        'netflix-premium' => 20000,
        'disney-hotstar' => 12000,
        'youtube-premium' => 10000,
        'canva-pro' => 6000,
        'capcut-pro' => 15000,
    ];

    public function run(): void
    {
        if (app()->isProduction() || Account::query()->exists()) {
            return;
        }

        $samples = [
            // [slug, email, password, profil, pin, status, terjual ke]
            ['netflix-premium', 'nf.satu@contoh.test', 'netflix123', 'Profil 1', '1111', AccountStatus::Available, null],
            ['netflix-premium', 'nf.satu@contoh.test', 'netflix123', 'Profil 2', '2222', AccountStatus::Sold, 'Budi (0812-3456-7890)'],
            ['netflix-premium', 'nf.dua@contoh.test', 'netflix123', 'Profil 1', '3333', AccountStatus::Available, null],
            ['disney-hotstar', 'ds.satu@contoh.test', 'disney123', 'Profil 1', '4444', AccountStatus::Available, null],
            ['youtube-premium', 'yt.satu@contoh.test', 'youtube123', null, null, AccountStatus::Problem, null],
            ['canva-pro', 'https://www.canva.com/brand/join?token=contoh', null, null, null, AccountStatus::Available, null],
            ['capcut-pro', 'cc.satu@contoh.test', '12345', null, null, AccountStatus::Available, null],
        ];

        foreach ($samples as $i => [$slug, $email, $password, $profile, $pin, $status, $soldTo]) {
            $product = Product::where('slug', $slug)->first();

            $product && Account::create([
                'product_id' => $product->id,
                'email' => $email,
                'password' => $password,
                'profile' => $profile,
                'pin' => $pin,
                'cogs' => self::COGS[$slug] ?? null,
                'status' => $status,
                'sold_to' => $soldTo,
                // Tanggal mulai bervariasi agar sisa masa aktifnya beragam.
                'started_at' => today()->subDays($i * 4)->toDateString(),
                'active_days' => 28,
            ]);
        }
    }
}
