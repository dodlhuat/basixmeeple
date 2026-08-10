<?php

namespace Database\Factories;

use App\Models\PlaySession;
use App\Models\SessionPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionPlayer>
 */
class SessionPlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'user_id' => null,
            'player_name' => $this->faker->firstName(),
            'is_winner' => $this->faker->boolean(30),
            'score' => $this->faker->optional()->numberBetween(0, 150),
        ];
    }
}
