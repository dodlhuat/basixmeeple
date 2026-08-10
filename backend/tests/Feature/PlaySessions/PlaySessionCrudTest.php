<?php

namespace Tests\Feature\PlaySessions;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\Game;
use App\Models\PlaySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaySessionCrudTest extends TestCase
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

    public function test_an_editor_can_log_a_play_session_with_players(): void
    {
        $editor = User::factory()->create();
        [$collection, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/collections/{$collection->id}/play-sessions", [
            'game_id' => $game->id,
            'played_at' => now()->toIso8601String(),
            'outcome' => 'win',
            'players' => [
                ['player_name' => 'Anna', 'is_winner' => true, 'score' => 42],
                ['player_name' => 'Ben', 'is_winner' => false, 'score' => 30],
            ],
        ]);

        $response->assertCreated();
        $playSession = PlaySession::firstOrFail();
        $this->assertCount(2, $playSession->players);
    }

    public function test_a_game_not_in_the_collection_is_rejected(): void
    {
        $editor = User::factory()->create();
        [$collection] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $otherGame = Game::factory()->create();
        Sanctum::actingAs($editor);

        $this->postJson("/api/collections/{$collection->id}/play-sessions", [
            'game_id' => $otherGame->id,
            'played_at' => now()->toIso8601String(),
            'players' => [],
        ])->assertUnprocessable();
    }

    public function test_a_viewer_cannot_log_a_play_session(): void
    {
        $viewer = User::factory()->create();
        [$collection, $game] = $this->collectionWithGame($viewer, CollectionRole::Viewer);
        Sanctum::actingAs($viewer);

        $this->postJson("/api/collections/{$collection->id}/play-sessions", [
            'game_id' => $game->id,
            'played_at' => now()->toIso8601String(),
            'players' => [],
        ])->assertForbidden();
    }

    public function test_updating_players_replaces_the_previous_list(): void
    {
        $editor = User::factory()->create();
        [$collection, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $playSession = PlaySession::factory()->create(['collection_id' => $collection->id, 'game_id' => $game->id]);
        $playSession->players()->create(['player_name' => 'Alt']);
        Sanctum::actingAs($editor);

        $response = $this->patchJson("/api/collections/{$collection->id}/play-sessions/{$playSession->id}", [
            'players' => [['player_name' => 'Neu']],
        ]);

        $response->assertOk();
        $this->assertSame(['Neu'], $playSession->players()->pluck('player_name')->all());
    }

    public function test_a_play_session_from_a_different_collection_returns_404(): void
    {
        $editor = User::factory()->create();
        [$collection] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $otherPlaySession = PlaySession::factory()->create();
        Sanctum::actingAs($editor);

        $this->getJson("/api/collections/{$collection->id}/play-sessions/{$otherPlaySession->id}")
            ->assertNotFound();
    }

    public function test_an_editor_can_delete_a_play_session(): void
    {
        $editor = User::factory()->create();
        [$collection, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $playSession = PlaySession::factory()->create(['collection_id' => $collection->id, 'game_id' => $game->id]);
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/collections/{$collection->id}/play-sessions/{$playSession->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('play_sessions', ['id' => $playSession->id]);
    }
}
