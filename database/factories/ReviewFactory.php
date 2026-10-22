<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'reviewer_name' => 'Rizky Anwar',
            'rating' => 5,
            'body' => 'Akun langsung dikirim dan aktif normal sampai sekarang.',
            'status' => Review::APPROVED,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => Review::PENDING]);
    }

    public function hidden(): static
    {
        return $this->state(['status' => Review::HIDDEN]);
    }
}
