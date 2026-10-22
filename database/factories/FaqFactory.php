<?php

namespace Database\Factories;

use App\Enums\FaqCategory;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => FaqCategory::General,
            'question' => 'Pertanyaan '.fake()->unique()->numberBetween(1, 99999).'?',
            'answer' => 'Jawaban singkat untuk pertanyaan ini.',
            'is_active' => true,
            'show_on_home' => false,
            'show_on_product' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function onHome(): static
    {
        return $this->state(['show_on_home' => true]);
    }

    public function onProduct(): static
    {
        return $this->state(['show_on_product' => true]);
    }
}
