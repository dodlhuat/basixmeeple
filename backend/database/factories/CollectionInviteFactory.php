<?php

namespace Database\Factories;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\CollectionInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CollectionInvite>
 */
class CollectionInviteFactory extends Factory
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
            'email' => $this->faker->unique()->safeEmail(),
            'role' => CollectionRole::Viewer,
            'token_hash' => hash('sha256', Str::random(64)),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function withToken(string $plaintextToken): static
    {
        return $this->state(fn () => ['token_hash' => hash('sha256', $plaintextToken)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }
}
