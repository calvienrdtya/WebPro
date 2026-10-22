<?php

namespace Database\Seeders;

use App\Models\Warranty;
use Illuminate\Database\Seeder;

/** Paket garansi contoh. Memakai firstOrCreate: garansi yang sudah ada (mis. sudah diedit admin) tidak ditimpa. */
class WarrantySeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => 'Garansi 7 Hari', 'price' => 5000, 'duration_days' => 7],
            ['name' => 'Garansi Penuh', 'price' => 10000, 'duration_days' => 28],
        ];

        foreach ($packages as $package) {
            Warranty::firstOrCreate(['name' => $package['name']], $package + ['is_active' => true, 'applies_to_all' => true]);
        }
    }
}
