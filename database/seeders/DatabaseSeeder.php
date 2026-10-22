<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun uji berpassword lemah: jangan pernah dibuat di production.
        if (app()->isProduction()) {
            return;
        }

        $this->seedUser('admin@gmail.com', 'Admin ProAkses', 'admin', isAdmin: true);
        $this->seedUser('user@proakses.test', 'User Contoh', 'password', isAdmin: false);

        $this->call(ProductSeeder::class);
        $this->call(WarrantySeeder::class);
        $this->call(VoucherSeeder::class);
        $this->call(FaqSeeder::class);
        $this->call(ReportSeeder::class);
        $this->call(AccountSeeder::class);
        $this->call(ReviewSeeder::class);
    }

    private function seedUser(string $email, string $name, string $password, bool $isAdmin): void
    {
        User::firstOrNew(['email' => $email])->forceFill([
            'name' => $name,
            'password' => $password,
            'is_admin' => $isAdmin,
            'email_verified_at' => now(),
        ])->save();
    }
}
