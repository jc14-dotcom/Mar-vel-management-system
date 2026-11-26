<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherRevision>
 */
class VoucherRevisionFactory extends Factory
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
            'revision_number' => 1,
            'snapshot' => [],
            'reason' => null,
            'actor_id' => User::factory(),
            'printed_at' => now(),
            'superseded_at' => null,
        ];
    }
}
