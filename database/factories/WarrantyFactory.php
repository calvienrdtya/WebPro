<?php

namespace Database\Factories;

use App\Models\Warranty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warranty>
 */
class WarrantyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Garansi '.fake()->unique()->numberBetween(1, 99999).' Hari',
            'price' => 5000,
            'duration_days' => 7,
            'is_active' => true,
        ];
    }

    public function forAllProducts(): static
    {
        return $this->state(['applies_to_all' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
