<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'title' => ucfirst($this->faker->words(2, true)),
            'bgg_id' => $this->faker->optional()->numberBetween(1000, 400000),
            'priority' => $this->faker->numberBetween(1, 5),
            'price_estimate' => $this->faker->optional()->randomFloat(2, 15, 80),
        ];
    }
}
