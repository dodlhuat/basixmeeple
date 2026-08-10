<?php

namespace Database\Seeders;

use App\Enums\CollectionRole;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Game;
use App\Models\Loan;
use App\Models\PlaySession;
use App\Models\SessionPlayer;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds a login-ready test user (test@example.com / password) plus, in
     * `local` only, a sample collection with games/sessions/wishlist/loan
     * data so the UI isn't empty right after `migrate --seed`. The demo
     * data is skipped outside `local` so a stray `db:seed` against a real
     * deploy can't pollute it — the test user itself still seeds everywhere
     * since it's also relied on as the very first account (see
     * [[basixmeeple-project]] memory: registration is invite-only).
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        if (! app()->environment('local')) {
            return;
        }

        $collection = Collection::factory()->create([
            'name' => 'Spieleschrank',
            'owner_id' => $user->id,
        ]);
        $collection->users()->attach($user, ['role' => CollectionRole::Owner]);

        $categories = collect(['Strategie', 'Familie', 'Party', 'Kennerspiel'])
            ->map(fn (string $name) => Category::factory()->create(['name' => $name]));

        $games = Game::factory()->count(6)->create();
        $playerNames = ['Anna', 'Ben', 'Cleo'];
        $sessionCountsByGame = [8, 5, 3, 2, 1, 1];

        foreach ($games as $index => $game) {
            $collection->games()->attach($game, [
                'location' => fake()->randomElement(['Regal A', 'Regal B', 'Schrank']),
                'condition' => fake()->randomElement(['neuwertig', 'gut', 'gebraucht']),
            ]);

            $game->categories()->attach($categories->random(random_int(1, 2))->pluck('id'));

            for ($i = 0; $i < ($sessionCountsByGame[$index] ?? 1); $i++) {
                $session = PlaySession::factory()->create([
                    'collection_id' => $collection->id,
                    'game_id' => $game->id,
                    'played_at' => now()->subMonths(random_int(0, 11))->subDays(random_int(0, 27)),
                ]);

                $winner = fake()->randomElement($playerNames);
                foreach ($playerNames as $name) {
                    SessionPlayer::factory()->create([
                        'session_id' => $session->id,
                        'player_name' => $name,
                        'is_winner' => $name === $winner,
                    ]);
                }
            }
        }

        WishlistItem::factory()->create([
            'collection_id' => $collection->id,
            'title' => 'Brass: Birmingham',
            'priority' => 5,
        ]);
        WishlistItem::factory()->create([
            'collection_id' => $collection->id,
            'title' => 'Everdell',
            'priority' => 3,
        ]);

        Loan::factory()->create([
            'game_id' => $games->first()->id,
            'borrower_name' => 'Max Mustermann',
        ]);
    }
}
