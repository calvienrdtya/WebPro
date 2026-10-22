<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'HEMAT'.fake()->unique()->numberBetween(1, 999999),
            'title' => 'Diskon 10%',
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
            'per_user_limit' => 1,
            'requires_claim' => true,
            'is_listed' => true,
            'is_active' => true,
            'applies_to_all' => true,
        ];
    }

    public function fixed(int $amount = 25000): static
    {
        return $this->state(['discount_type' => DiscountType::Fixed, 'discount_value' => $amount]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
