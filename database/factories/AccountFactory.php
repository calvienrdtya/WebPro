<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'pass'.fake()->numberBetween(100, 999),
            'profile' => fake()->firstName(),
            'pin' => (string) fake()->numberBetween(1000, 9999),
            'cogs' => 20000,
            'status' => AccountStatus::Available,
            'started_at' => today()->toDateString(),
            'active_days' => 28,
        ];
    }

    public function status(AccountStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
