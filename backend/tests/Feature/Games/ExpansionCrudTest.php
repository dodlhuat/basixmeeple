<?php

namespace Tests\Feature\Games;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\Expansion;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpansionCrudTest extends TestCase
{
    use RefreshDatabase;

    private function collectionWithGame(User $member, CollectionRole $role): array
    {
        $owner = $role === CollectionRole::Owner ? $member : User::factory()->create();
        $collection = Collection::factory()->create(['owner_id' => $owner->id]);
        $collection->users()->attach($owner, ['role' => CollectionRole::Owner]);

        if ($role !== CollectionRole::Owner) {
            $collection->users()->attach($member, ['role' => $role]);
        }

        $game = Game::factory()->create();
        $collection->games()->attach($game);

        return [$collection, $game];
    }

    public function test_an_editor_can_create_an_expansion(): void
    {
        $editor = User::factory()->create();
        [, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/games/{$game->id}/expansions", ['title' => 'Prelude']);

        $response->assertCreated()->assertJsonPath('title', 'Prelude');
        $this->assertDatabaseHas('expansions', ['base_game_id' => $game->id, 'title' => 'Prelude']);
    }

    public function test_a_viewer_cannot_create_an_expansion(): void
    {
        $viewer = User::factory()->create();
        [, $game] = $this->collectionWithGame($viewer, CollectionRole::Viewer);
        Sanctum::actingAs($viewer);

        $this->postJson("/api/games/{$game->id}/expansions", ['title' => 'Prelude'])->assertForbidden();
    }

    public function test_an_editor_can_update_an_expansion(): void
    {
        $editor = User::factory()->create();
        [, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $expansion = Expansion::factory()->create(['base_game_id' => $game->id]);
        Sanctum::actingAs($editor);

        $this->patchJson("/api/games/{$game->id}/expansions/{$expansion->id}", ['title' => 'Neuer Titel'])
            ->assertOk()
            ->assertJsonPath('title', 'Neuer Titel');
    }

    public function test_an_expansion_from_a_different_game_returns_404(): void
    {
        $editor = User::factory()->create();
        [, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $otherExpansion = Expansion::factory()->create();
        Sanctum::actingAs($editor);

        $this->patchJson("/api/games/{$game->id}/expansions/{$otherExpansion->id}", ['title' => 'X'])
            ->assertNotFound();
    }

    public function test_an_editor_can_delete_an_expansion(): void
    {
        $editor = User::factory()->create();
        [, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $expansion = Expansion::factory()->create(['base_game_id' => $game->id]);
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/games/{$game->id}/expansions/{$expansion->id}")->assertNoContent();
        $this->assertDatabaseMissing('expansions', ['id' => $expansion->id]);
    }

    public function test_a_viewer_cannot_delete_an_expansion(): void
    {
        $viewer = User::factory()->create();
        [, $game] = $this->collectionWithGame($viewer, CollectionRole::Viewer);
        $expansion = Expansion::factory()->create(['base_game_id' => $game->id]);
        Sanctum::actingAs($viewer);

        $this->deleteJson("/api/games/{$game->id}/expansions/{$expansion->id}")->assertForbidden();
    }
}
