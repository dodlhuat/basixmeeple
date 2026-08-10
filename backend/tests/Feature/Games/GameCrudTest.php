<?php

namespace Tests\Feature\Games;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameCrudTest extends TestCase
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

    public function test_an_editor_can_create_a_game_and_it_is_attached_to_the_collection(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/collections/{$collection->id}/games", [
            'title' => 'Terraforming Mars',
            'min_players' => 1,
            'max_players' => 5,
            'location' => 'Regal A',
            'condition' => 'neuwertig',
        ]);

        $response->assertCreated();
        $game = Game::where('title', 'Terraforming Mars')->firstOrFail();
        $this->assertTrue($collection->games()->where('games.id', $game->id)->exists());
        $this->assertSame('Regal A', $collection->games()->where('games.id', $game->id)->first()->pivot->location);
    }

    public function test_a_viewer_cannot_create_a_game(): void
    {
        $viewer = User::factory()->create();
        $collection = $this->collectionWithMember($viewer, CollectionRole::Viewer);
        Sanctum::actingAs($viewer);

        $this->postJson("/api/collections/{$collection->id}/games", ['title' => 'Wingspan'])
            ->assertForbidden();
    }

    public function test_a_non_member_cannot_view_a_game(): void
    {
        $game = Game::factory()->create();
        $collection = Collection::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/games/{$game->id}")->assertForbidden();
    }

    public function test_a_member_can_view_a_game(): void
    {
        $viewer = User::factory()->create();
        $collection = $this->collectionWithMember($viewer, CollectionRole::Viewer);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs($viewer);

        $this->getJson("/api/games/{$game->id}")->assertOk()->assertJsonPath('id', $game->id);
    }

    public function test_an_editor_can_update_a_game(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs($editor);

        $this->patchJson("/api/games/{$game->id}", ['title' => 'Neuer Titel'])
            ->assertOk()
            ->assertJsonPath('title', 'Neuer Titel');
    }

    public function test_a_viewer_cannot_update_a_game(): void
    {
        $viewer = User::factory()->create();
        $collection = $this->collectionWithMember($viewer, CollectionRole::Viewer);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs($viewer);

        $this->patchJson("/api/games/{$game->id}", ['title' => 'Neuer Titel'])->assertForbidden();
    }

    public function test_attaching_an_existing_game_to_a_second_collection(): void
    {
        $editor = User::factory()->create();
        $firstCollection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $secondCollection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $firstCollection->games()->attach($game);
        Sanctum::actingAs($editor);

        $this->postJson("/api/collections/{$secondCollection->id}/games/{$game->id}/attach", ['location' => 'Keller'])
            ->assertOk();

        $this->assertTrue($secondCollection->games()->where('games.id', $game->id)->exists());
        $this->assertTrue($firstCollection->games()->where('games.id', $game->id)->exists());
    }

    public function test_detaching_a_game_from_one_collection_leaves_the_other_untouched(): void
    {
        $editor = User::factory()->create();
        $firstCollection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $secondCollection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $firstCollection->games()->attach($game);
        $secondCollection->games()->attach($game);
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/collections/{$firstCollection->id}/games/{$game->id}")->assertNoContent();

        $this->assertFalse($firstCollection->games()->where('games.id', $game->id)->exists());
        $this->assertTrue($secondCollection->games()->where('games.id', $game->id)->exists());
        $this->assertDatabaseHas('games', ['id' => $game->id]);
    }

    public function test_an_editor_can_delete_a_game(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/games/{$game->id}")->assertNoContent();
        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    }
}
