<?php

namespace Tests\Feature\Models;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\Game;
use App\Models\PlaySession;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_relation_resolves_to_the_owning_user(): void
    {
        $owner = User::factory()->create();
        $collection = Collection::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($collection->owner->is($owner));
        $this->assertTrue($owner->ownedCollections()->first()->is($collection));
    }

    public function test_users_pivot_role_is_cast_to_the_collection_role_enum(): void
    {
        $collection = Collection::factory()->create();
        $user = User::factory()->create();

        $collection->users()->attach($user, ['role' => CollectionRole::Editor]);

        $pivotRole = $collection->users()->first()->pivot->role;

        $this->assertInstanceOf(CollectionRole::class, $pivotRole);
        $this->assertSame(CollectionRole::Editor, $pivotRole);
    }

    public function test_a_game_can_only_appear_once_per_collection(): void
    {
        $collection = Collection::factory()->create();
        $game = Game::factory()->create();

        $collection->games()->attach($game, ['location' => 'Regal 1']);

        $this->expectException(QueryException::class);

        $collection->games()->attach($game, ['location' => 'Regal 2']);
    }

    public function test_deleting_a_collection_cascades_to_collection_games_and_collection_user(): void
    {
        $collection = Collection::factory()->create();
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $collection->games()->attach($game, ['location' => 'Regal 1']);
        $collection->users()->attach($user, ['role' => CollectionRole::Viewer]);

        $collection->delete();

        $this->assertDatabaseMissing('collection_games', ['collection_id' => $collection->id]);
        $this->assertDatabaseMissing('collection_user', ['collection_id' => $collection->id]);
    }

    public function test_deleting_a_collection_cascades_to_play_sessions_and_wishlist_items(): void
    {
        $collection = Collection::factory()->create();
        $session = PlaySession::factory()->create(['collection_id' => $collection->id]);
        $wishlistItem = WishlistItem::factory()->create(['collection_id' => $collection->id]);

        $collection->delete();

        $this->assertDatabaseMissing('play_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('wishlist_items', ['id' => $wishlistItem->id]);
    }
}
