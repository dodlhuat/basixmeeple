<?php

namespace Tests\Feature\Sync;

use App\Enums\CollectionRole;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Game;
use App\Models\Loan;
use App\Models\PlaySession;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_only_includes_data_from_the_users_own_collections(): void
    {
        $member = User::factory()->create();
        $ownCollection = Collection::factory()->create(['owner_id' => $member->id]);
        $ownCollection->users()->attach($member, ['role' => CollectionRole::Owner]);
        $ownGame = Game::factory()->create();
        $ownCollection->games()->attach($ownGame);
        WishlistItem::factory()->create(['collection_id' => $ownCollection->id]);
        Loan::factory()->create(['game_id' => $ownGame->id]);
        PlaySession::factory()->create(['collection_id' => $ownCollection->id, 'game_id' => $ownGame->id]);

        $otherOwner = User::factory()->create();
        $otherCollection = Collection::factory()->create(['owner_id' => $otherOwner->id]);
        $otherCollection->users()->attach($otherOwner, ['role' => CollectionRole::Owner]);
        $otherGame = Game::factory()->create();
        $otherCollection->games()->attach($otherGame);
        WishlistItem::factory()->create(['collection_id' => $otherCollection->id]);
        Loan::factory()->create(['game_id' => $otherGame->id]);

        Sanctum::actingAs($member);

        $response = $this->postJson('/api/sync', ['operations' => []]);

        $response->assertOk();
        $snapshot = $response->json('snapshot');

        $this->assertSame([$ownCollection->id], array_column($snapshot['collections'], 'id'));
        $this->assertSame([$ownGame->id], array_column($snapshot['games'], 'id'));
        $this->assertCount(1, $snapshot['wishlist_items']);
        $this->assertCount(1, $snapshot['loans']);
        $this->assertCount(1, $snapshot['play_sessions']);
    }

    public function test_categories_are_included_globally_regardless_of_collection(): void
    {
        Category::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/sync', ['operations' => []]);

        $this->assertCount(3, $response->json('snapshot.categories'));
    }

    public function test_snapshot_includes_co_members_of_shared_collections(): void
    {
        $owner = User::factory()->create();
        $collection = Collection::factory()->create(['owner_id' => $owner->id]);
        $collection->users()->attach($owner, ['role' => CollectionRole::Owner]);
        $viewer = User::factory()->create();
        $collection->users()->attach($viewer, ['role' => CollectionRole::Viewer]);
        Sanctum::actingAs($viewer);

        $response = $this->postJson('/api/sync', ['operations' => []]);

        $userIds = array_column($response->json('snapshot.users'), 'id');
        $this->assertContains($owner->id, $userIds);
        $this->assertContains($viewer->id, $userIds);
    }
}
