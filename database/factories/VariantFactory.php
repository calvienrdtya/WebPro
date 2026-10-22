<?php

namespace Database\Factories;

use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Variant>
 */
class VariantFactory extends Factory
{
    public function definition(): array
    {
        $months = fake()->unique()->numberBetween(1, 12);

        return [
            'title' => "{$months} Bulan",
            'months' => $months,
            'is_active' => true,
        ];
    }

    public function months(int $months, ?string $title = null): static
    {
        return $this->state(['months' => $months, 'title' => $title ?? "{$months} Bulan"]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
