<?php

namespace Tests\Feature\Wishlist;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WishlistItemCrudTest extends TestCase
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

    public function test_an_editor_can_add_a_wishlist_item(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/collections/{$collection->id}/wishlist-items", [
            'title' => 'Brass: Birmingham',
            'priority' => 5,
        ]);

        $response->assertCreated()->assertJsonPath('title', 'Brass: Birmingham');
    }

    public function test_a_viewer_cannot_add_a_wishlist_item(): void
    {
        $viewer = User::factory()->create();
        $collection = $this->collectionWithMember($viewer, CollectionRole::Viewer);
        Sanctum::actingAs($viewer);

        $this->postJson("/api/collections/{$collection->id}/wishlist-items", ['title' => 'Brass: Birmingham'])
            ->assertForbidden();
    }

    public function test_a_viewer_can_list_wishlist_items(): void
    {
        $viewer = User::factory()->create();
        $collection = $this->collectionWithMember($viewer, CollectionRole::Viewer);
        WishlistItem::factory()->count(2)->create(['collection_id' => $collection->id]);
        Sanctum::actingAs($viewer);

        $this->getJson("/api/collections/{$collection->id}/wishlist-items")->assertOk()->assertJsonCount(2);
    }

    public function test_an_editor_can_update_a_wishlist_item(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $item = WishlistItem::factory()->create(['collection_id' => $collection->id]);
        Sanctum::actingAs($editor);

        $this->patchJson("/api/collections/{$collection->id}/wishlist-items/{$item->id}", ['priority' => 1])
            ->assertOk()
            ->assertJsonPath('priority', 1);
    }

    public function test_a_wishlist_item_from_a_different_collection_returns_404(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $otherItem = WishlistItem::factory()->create();
        Sanctum::actingAs($editor);

        $this->patchJson("/api/collections/{$collection->id}/wishlist-items/{$otherItem->id}", ['priority' => 1])
            ->assertNotFound();
    }

    public function test_an_editor_can_delete_a_wishlist_item(): void
    {
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $item = WishlistItem::factory()->create(['collection_id' => $collection->id]);
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/collections/{$collection->id}/wishlist-items/{$item->id}")->assertNoContent();
        $this->assertDatabaseMissing('wishlist_items', ['id' => $item->id]);
    }
}
