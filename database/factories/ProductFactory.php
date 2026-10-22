<?php

namespace Database\Factories;

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' Premium';

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'category' => fake()->randomElement(ProductCategory::cases()),
            'description' => fake()->sentence(12),
            'highlights' => [fake()->sentence(4), fake()->sentence(4)],
            'specs' => [['label' => 'Jenis', 'value' => 'Akun sharing']],
            'price' => 45000,
            'discount_percent' => 20,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
