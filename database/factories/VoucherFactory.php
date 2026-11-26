<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
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
            'voucher_number' => 'DV-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'voucher_date' => fake()->date(),
            'payee' => fake()->company(),
            'project_id' => Project::factory(),
            'project_name_snapshot' => fake()->company(),
            'scope_of_work' => fake()->sentence(),
            'expense_category' => fake()->word(),
            'payment_type' => fake()->randomElement(['full', 'partial']),
            'created_by' => User::factory(),
            'prepared_by' => fake()->name(),
            'total_amount' => '0.00',
            'status' => 'draft',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Voucher $voucher): void {
            $voucher->project_name_snapshot = Project::query()
                ->whereKey($voucher->project_id)
                ->value('name');
        });
    }
}
