<?php

namespace Tests\Feature\Loans;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\Game;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanCrudTest extends TestCase
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

    public function test_an_editor_can_create_a_loan(): void
    {
        $editor = User::factory()->create();
        [$collection, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/collections/{$collection->id}/loans", [
            'game_id' => $game->id,
            'borrower_name' => 'Max Mustermann',
            'loaned_at' => now()->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('loans', ['game_id' => $game->id, 'borrower_name' => 'Max Mustermann']);
    }

    public function test_a_game_not_in_the_collection_is_rejected(): void
    {
        $editor = User::factory()->create();
        [$collection] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $otherGame = Game::factory()->create();
        Sanctum::actingAs($editor);

        $this->postJson("/api/collections/{$collection->id}/loans", [
            'game_id' => $otherGame->id,
            'borrower_name' => 'Max Mustermann',
            'loaned_at' => now()->toIso8601String(),
        ])->assertUnprocessable();
    }

    public function test_a_viewer_cannot_create_a_loan(): void
    {
        $viewer = User::factory()->create();
        [$collection, $game] = $this->collectionWithGame($viewer, CollectionRole::Viewer);
        Sanctum::actingAs($viewer);

        $this->postJson("/api/collections/{$collection->id}/loans", [
            'game_id' => $game->id,
            'borrower_name' => 'Max Mustermann',
            'loaned_at' => now()->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_an_editor_can_mark_a_loan_as_returned(): void
    {
        $editor = User::factory()->create();
        [$collection, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $loan = Loan::factory()->create(['game_id' => $game->id]);
        Sanctum::actingAs($editor);

        $response = $this->patchJson("/api/collections/{$collection->id}/loans/{$loan->id}", [
            'returned_at' => now()->toIso8601String(),
        ]);

        $response->assertOk();
        $this->assertNotNull($loan->fresh()->returned_at);
    }

    public function test_a_loan_for_a_game_outside_the_collection_returns_404(): void
    {
        $editor = User::factory()->create();
        [$collection] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $otherLoan = Loan::factory()->create();
        Sanctum::actingAs($editor);

        $this->patchJson("/api/collections/{$collection->id}/loans/{$otherLoan->id}", [
            'returned_at' => now()->toIso8601String(),
        ])->assertNotFound();
    }

    public function test_an_editor_can_delete_a_loan(): void
    {
        $editor = User::factory()->create();
        [$collection, $game] = $this->collectionWithGame($editor, CollectionRole::Editor);
        $loan = Loan::factory()->create(['game_id' => $game->id]);
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/collections/{$collection->id}/loans/{$loan->id}")->assertNoContent();
        $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
    }
}
