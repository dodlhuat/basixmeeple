<?php

namespace Tests\Feature\Collections;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\CollectionInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionInviteTest extends TestCase
{
    use RefreshDatabase;

    private function ownedCollection(User $owner): Collection
    {
        $collection = Collection::factory()->create(['owner_id' => $owner->id]);
        $collection->users()->attach($owner, ['role' => CollectionRole::Owner]);

        return $collection;
    }

    public function test_owner_can_list_pending_invites(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        $invite = CollectionInvite::factory()->create(['collection_id' => $collection->id]);
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/collections/{$collection->id}/invites");

        $response->assertOk();
        $response->assertJsonPath('0.id', $invite->id);
        $response->assertJsonMissingPath('0.token_hash');
    }

    public function test_owner_can_revoke_a_pending_invite(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        $invite = CollectionInvite::factory()->create(['collection_id' => $collection->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/collections/{$collection->id}/invites/{$invite->id}")->assertNoContent();

        $this->assertDatabaseMissing('collection_invites', ['id' => $invite->id]);
    }

    public function test_a_non_owner_cannot_list_or_revoke_invites(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        $viewer = User::factory()->create();
        $collection->users()->attach($viewer, ['role' => CollectionRole::Viewer]);
        $invite = CollectionInvite::factory()->create(['collection_id' => $collection->id]);
        Sanctum::actingAs($viewer);

        $this->getJson("/api/collections/{$collection->id}/invites")->assertForbidden();
        $this->deleteJson("/api/collections/{$collection->id}/invites/{$invite->id}")->assertForbidden();
    }
}
