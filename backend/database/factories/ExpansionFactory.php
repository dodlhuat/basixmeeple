<?php

namespace Database\Factories;

use App\Models\Expansion;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expansion>
 */
class ExpansionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_game_id' => Game::factory(),
            'title' => ucfirst($this->faker->words(2, true)),
            'bgg_id' => $this->faker->unique()->numberBetween(1000, 400000),
            'cover_url' => $this->faker->imageUrl(400, 400, 'games'),
        ];
    }
}
