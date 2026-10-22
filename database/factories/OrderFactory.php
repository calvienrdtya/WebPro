<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Order::generateCode(),
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'product_name' => 'Netflix Premium',
            'variant_title' => '1 Bulan',
            'months' => 1,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '081234567890',
            'list_price' => 50000,
            'product_price' => 40000,
            'warranty_price' => 0,
            'voucher_discount' => 0,
            'service_fee' => 0,
            'total' => 40000,
            'expires_at' => now()->addMinutes(15),
        ];
    }

    /** `status` tidak mass-assignable, jadi diisi lewat afterMaking/afterCreating. */
    public function status(OrderStatus $status): static
    {
        return $this->afterMaking(fn (Order $o) => $o->forceFill(['status' => $status]));
    }
}
