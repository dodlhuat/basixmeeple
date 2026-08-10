<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loanedAt = $this->faker->dateTimeBetween('-2 months', 'now');

        return [
            'game_id' => Game::factory(),
            'borrower_name' => $this->faker->name(),
            'borrower_user_id' => null,
            'loaned_at' => $loanedAt,
            'due_date' => $this->faker->optional()->dateTimeBetween($loanedAt, '+1 month'),
            'returned_at' => null,
        ];
    }
}
