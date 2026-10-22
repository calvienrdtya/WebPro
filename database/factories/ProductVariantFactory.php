<?php

namespace Database\Factories;

use App\Enums\PriceMode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_id' => Variant::factory(),
            'price_mode' => PriceMode::Custom,
            'price' => 45000,
            'discount_percent' => 0,
        ];
    }

    public function fromProduct(): static
    {
        return $this->state(['price_mode' => PriceMode::FromProduct, 'price' => null, 'discount_percent' => 0]);
    }
}
