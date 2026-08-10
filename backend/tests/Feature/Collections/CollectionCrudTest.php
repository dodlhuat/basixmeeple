<?php

namespace Tests\Feature\Collections;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionCrudTest extends TestCase
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

    public function test_creating_a_collection_makes_the_creator_its_owner(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/collections', ['name' => 'Spieleschrank']);

        $response->assertCreated();
        $collection = Collection::findOrFail($response->json('id'));

        $this->assertTrue($collection->owner->is($user));
        $this->assertSame(CollectionRole::Owner, $collection->roleFor($user));
    }

    public function test_index_only_returns_collections_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $ownCollection = $this->collectionWithMember($user, CollectionRole::Editor);
        $this->collectionWithMember(User::factory()->create(), CollectionRole::Owner);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/collections');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $ownCollection->id);
    }

    public function test_a_non_member_cannot_view_a_collection(): void
    {
        $collection = Collection::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/collections/{$collection->id}")->assertForbidden();
    }

    public function test_a_member_can_view_a_collection(): void
    {
        $user = User::factory()->create();
        $collection = $this->collectionWithMember($user, CollectionRole::Viewer);
        Sanctum::actingAs($user);

        $this->getJson("/api/collections/{$collection->id}")->assertOk();
    }

    public function test_only_the_owner_can_rename_a_collection(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $this->patchJson("/api/collections/{$collection->id}", ['name' => 'Neuer Name'])
            ->assertForbidden();

        Sanctum::actingAs($collection->owner);

        $this->patchJson("/api/collections/{$collection->id}", ['name' => 'Neuer Name'])
            ->assertOk()
            ->assertJsonPath('name', 'Neuer Name');
    }

    public function test_only_the_owner_can_delete_a_collection(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/collections/{$collection->id}")->assertForbidden();

        Sanctum::actingAs($collection->owner);

        $this->deleteJson("/api/collections/{$collection->id}")->assertNoContent();
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }
}
