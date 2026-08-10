<?php

namespace Database\Factories;

use App\Enums\PlaySessionOutcome;
use App\Models\Collection;
use App\Models\Game;
use App\Models\PlaySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaySession>
 */
class PlaySessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'collection_id' => Collection::factory(),
            'played_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'duration_min' => $this->faker->numberBetween(20, 180),
            'outcome' => $this->faker->optional()->randomElement(PlaySessionOutcome::cases()),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
