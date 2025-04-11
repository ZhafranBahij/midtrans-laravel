<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(),
            'discount_percentange' => random_int(10, 50),
            'start_date' => now(),
            'end_date' => now()->addDays(random_int(1, 30)),
            'description' => fake()->paragraph(),
        ];
    }
}
