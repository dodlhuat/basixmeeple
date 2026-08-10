<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minPlayers = $this->faker->numberBetween(1, 4);

        return [
            'title' => ucfirst($this->faker->words(2, true)),
            'bgg_id' => $this->faker->unique()->numberBetween(1000, 400000),
            'publisher' => $this->faker->company(),
            'min_players' => $minPlayers,
            'max_players' => $minPlayers + $this->faker->numberBetween(0, 4),
            'play_time_min' => $this->faker->randomElement([15, 30, 45, 60]),
            'play_time_max' => $this->faker->randomElement([60, 90, 120, 180]),
            'min_age' => $this->faker->randomElement([6, 8, 10, 12, 14]),
            'weight_complexity' => $this->faker->randomFloat(2, 1, 5),
            'description' => $this->faker->paragraph(),
            'cover_url' => $this->faker->imageUrl(400, 400, 'games'),
            'rulebook_path' => null,
            'edition' => $this->faker->optional()->randomElement(['1. Auflage', '2. Auflage', 'Jubiläumsedition']),
            'language' => $this->faker->randomElement(['de', 'en']),
            'condition_notes' => $this->faker->optional()->sentence(),
            'purchase_price' => $this->faker->optional()->randomFloat(2, 10, 90),
        ];
    }
}
