<?php

namespace Database\Factories;

use App\Models\Voucher;
use App\Models\VoucherItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherItem>
 */
class VoucherItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory(),
            'description' => fake()->sentence(),
            'amount' => '10.00',
            'line_type' => 'expense',
            'sort_order' => 0,
        ];
    }
}
