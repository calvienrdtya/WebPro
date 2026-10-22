<?php

namespace Database\Factories;

use App\Enums\ReportCategory;
use App\Models\CustomerReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReport>
 */
class CustomerReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => ReportCategory::Account,
            'subject' => 'Akun tidak bisa login',
            'body' => 'Akun yang saya terima tidak bisa dipakai login sejak tadi pagi.',
            'status' => CustomerReport::OPEN,
        ];
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => CustomerReport::RESOLVED,
            'resolution' => 'other',
            'resolved_at' => now(),
        ]);
    }
}
