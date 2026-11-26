<?php

namespace Database\Factories;

use App\Models\VoucherNumberSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherNumberSequence>
 */
class VoucherNumberSequenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => now()->year,
            'last_number' => 0,
        ];
    }
}
