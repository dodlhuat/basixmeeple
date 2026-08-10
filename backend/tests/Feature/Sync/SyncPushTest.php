<?php

namespace Tests\Feature\Sync;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\Game;
use App\Models\PlaySession;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncPushTest extends TestCase
{
    use RefreshDatabase;

    private function collectionWithMember(User $member, CollectionRole $role): Collection
    {
        $owner = $role === CollectionRole::Owner ? $member : User::factory()->create();
        $collection = Collection::factory()->create(['owner_id' => $owner->id]);
        $collection->users()->attach($owner, ['role' => CollectionRole::Owner]);

        if ($role !== CollectionRole::Owner) {
            $collection->users()->attach($member, ['role' => $role]);
        }

        return $collection;
    }

    private function sync(array $operations): TestResponse
    {
        return $this->postJson('/api/sync', ['operations' => $operations]);
    }

    public function test_a_new_wishlist_item_is_created_with_the_client_generated_id(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $clientId = (string) Str::uuid();
        Sanctum::actingAs($editor);

        $response = $this->sync([[
            'entity' => 'wishlist_items',
            'entity_id' => $clientId,
            'operation' => 'create',
            'payload' => [
                'collection_id' => $collection->id,
                'title' => 'Ark Nova',
                'priority' => 4,
                'updated_at' => now()->toIso8601String(),
            ],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertOk();
        $response->assertJsonPath('results.0.status', 'applied');
        $this->assertDatabaseHas('wishlist_items', ['id' => $clientId, 'title' => 'Ark Nova']);
    }

    public function test_a_viewer_push_is_rejected(): void
    {
        $viewer = User::factory()->create();
        $collection = $this->collectionWithMember($viewer, CollectionRole::Viewer);
        Sanctum::actingAs($viewer);

        $response = $this->sync([[
            'entity' => 'wishlist_items',
            'entity_id' => (string) Str::uuid(),
            'operation' => 'create',
            'payload' => ['collection_id' => $collection->id, 'title' => 'Ark Nova', 'updated_at' => now()->toIso8601String()],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertOk();
        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertDatabaseMissing('wishlist_items', ['title' => 'Ark Nova']);
    }

    public function test_a_newer_client_edit_wins_last_write_wins(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $item = WishlistItem::factory()->create(['collection_id' => $collection->id, 'title' => 'Alt', 'updated_at' => now()->subDay()]);
        Sanctum::actingAs($editor);

        $response = $this->sync([[
            'entity' => 'wishlist_items',
            'entity_id' => $item->id,
            'operation' => 'update',
            'payload' => ['collection_id' => $collection->id, 'title' => 'Neu', 'updated_at' => now()->toIso8601String()],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'applied');
        $this->assertSame('Neu', $item->fresh()->title);
    }

    public function test_an_older_client_edit_is_skipped(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $item = WishlistItem::factory()->create(['collection_id' => $collection->id, 'title' => 'Aktuell', 'updated_at' => now()]);
        Sanctum::actingAs($editor);

        $response = $this->sync([[
            'entity' => 'wishlist_items',
            'entity_id' => $item->id,
            'operation' => 'update',
            'payload' => ['collection_id' => $collection->id, 'title' => 'Veraltet', 'updated_at' => now()->subDay()->toIso8601String()],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'skipped');
        $this->assertSame('Aktuell', $item->fresh()->title);
    }

    public function test_deleting_an_already_deleted_record_is_treated_as_applied(): void
    {
        $editor = User::factory()->create();
        $this->collectionWithMember($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $response = $this->sync([[
            'entity' => 'wishlist_items',
            'entity_id' => (string) Str::uuid(),
            'operation' => 'delete',
            'payload' => null,
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'applied');
    }

    public function test_any_authenticated_user_can_push_a_new_bare_game(): void
    {
        $user = User::factory()->create();
        $clientId = (string) Str::uuid();
        Sanctum::actingAs($user);

        $response = $this->sync([[
            'entity' => 'games',
            'entity_id' => $clientId,
            'operation' => 'create',
            'payload' => ['title' => 'Neu erfasst', 'updated_at' => now()->toIso8601String()],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'applied');
        $this->assertDatabaseHas('games', ['id' => $clientId, 'title' => 'Neu erfasst']);
    }

    public function test_updating_a_game_requires_editor_role_in_a_collection_containing_it(): void
    {
        $outsider = User::factory()->create();
        $game = Game::factory()->create(['title' => 'Alt']);
        Sanctum::actingAs($outsider);

        $response = $this->sync([[
            'entity' => 'games',
            'entity_id' => $game->id,
            'operation' => 'update',
            'payload' => ['title' => 'Neu', 'updated_at' => now()->toIso8601String()],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame('Alt', $game->fresh()->title);
    }

    public function test_pushing_a_loan_is_authorized_via_the_games_collection(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        $clientId = (string) Str::uuid();
        Sanctum::actingAs($editor);

        $response = $this->sync([[
            'entity' => 'loans',
            'entity_id' => $clientId,
            'operation' => 'create',
            'payload' => [
                'game_id' => $game->id,
                'borrower_name' => 'Max',
                'loaned_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'applied');
        $this->assertDatabaseHas('loans', ['id' => $clientId, 'borrower_name' => 'Max']);
    }

    public function test_pushing_a_loan_for_a_game_outside_any_of_the_users_collections_is_rejected(): void
    {
        $editor = User::factory()->create();
        $this->collectionWithMember($editor, CollectionRole::Editor);
        $unrelatedGame = Game::factory()->create();
        Sanctum::actingAs($editor);

        $response = $this->sync([[
            'entity' => 'loans',
            'entity_id' => (string) Str::uuid(),
            'operation' => 'create',
            'payload' => [
                'game_id' => $unrelatedGame->id,
                'borrower_name' => 'Max',
                'loaned_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'rejected');
    }

    public function test_pushing_a_session_player_is_authorized_via_the_play_sessions_collection(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        $playSession = PlaySession::factory()->create(['collection_id' => $collection->id, 'game_id' => $game->id]);
        $clientId = (string) Str::uuid();
        Sanctum::actingAs($editor);

        $response = $this->sync([[
            'entity' => 'session_players',
            'entity_id' => $clientId,
            'operation' => 'create',
            'payload' => [
                'session_id' => $playSession->id,
                'player_name' => 'Anna',
                'updated_at' => now()->toIso8601String(),
            ],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'applied');
        $this->assertDatabaseHas('session_players', ['id' => $clientId, 'player_name' => 'Anna']);
    }

    public function test_pushing_a_collection_games_pivot_row(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $clientId = (string) Str::uuid();
        Sanctum::actingAs($editor);

        $response = $this->sync([[
            'entity' => 'collection_games',
            'entity_id' => $clientId,
            'operation' => 'create',
            'payload' => [
                'collection_id' => $collection->id,
                'game_id' => $game->id,
                'location' => 'Regal',
                'updated_at' => now()->toIso8601String(),
            ],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertJsonPath('results.0.status', 'applied');
        $this->assertDatabaseHas('collection_games', ['id' => $clientId, 'collection_id' => $collection->id, 'game_id' => $game->id]);
    }

    public function test_collections_entity_is_rejected_by_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->sync([[
            'entity' => 'collections',
            'entity_id' => (string) Str::uuid(),
            'operation' => 'update',
            'payload' => ['name' => 'Umbenannt'],
            'queued_at' => now()->toIso8601String(),
        ]]);

        $response->assertUnprocessable();
    }

    public function test_response_includes_a_snapshot(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->sync([]);

        $response->assertOk();
        $response->assertJsonStructure(['results', 'snapshot' => [
            'users', 'collections', 'collection_user', 'games', 'expansions',
            'categories', 'game_category', 'collection_games', 'play_sessions',
            'session_players', 'loans', 'wishlist_items',
        ]]);
    }
}
