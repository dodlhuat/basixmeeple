<?php

namespace Tests\Feature\Collections;

use App\Enums\CollectionRole;
use App\Mail\CollectionInviteMail;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionMemberTest extends TestCase
{
    use RefreshDatabase;

    private function ownedCollection(User $owner): Collection
    {
        $collection = Collection::factory()->create(['owner_id' => $owner->id]);
        $collection->users()->attach($owner, ['role' => CollectionRole::Owner]);

        return $collection;
    }

    public function test_owner_can_add_an_existing_user_by_email(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        $existingUser = User::factory()->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/collections/{$collection->id}/members", [
            'email' => $existingUser->email,
            'role' => 'editor',
        ]);

        $response->assertCreated();
        $this->assertSame(CollectionRole::Editor, $collection->roleFor($existingUser));
    }

    public function test_owner_inviting_an_unknown_email_creates_a_pending_invite_and_sends_mail(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/collections/{$collection->id}/members", [
            'email' => 'unbekannt@example.com',
            'role' => 'viewer',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('collection_invites', [
            'collection_id' => $collection->id,
            'email' => 'unbekannt@example.com',
            'role' => 'viewer',
        ]);
        Mail::assertSent(CollectionInviteMail::class);
    }

    public function test_inviting_an_existing_member_again_fails(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        $member = User::factory()->create();
        $collection->users()->attach($member, ['role' => CollectionRole::Viewer]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/collections/{$collection->id}/members", [
            'email' => $member->email,
            'role' => 'editor',
        ])->assertUnprocessable();
    }

    public function test_a_non_owner_cannot_invite_members(): void
    {
        $editor = User::factory()->create();
        $collection = $this->ownedCollection(User::factory()->create());
        $collection->users()->attach($editor, ['role' => CollectionRole::Editor]);
        Sanctum::actingAs($editor);

        $this->postJson("/api/collections/{$collection->id}/members", [
            'email' => 'wer@example.com',
            'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_owner_can_change_a_members_role(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        $member = User::factory()->create();
        $collection->users()->attach($member, ['role' => CollectionRole::Editor]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/collections/{$collection->id}/members/{$member->id}", ['role' => 'viewer'])
            ->assertOk();

        $this->assertSame(CollectionRole::Viewer, $collection->roleFor($member));
    }

    public function test_the_owners_role_cannot_be_changed(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/collections/{$collection->id}/members/{$owner->id}", ['role' => 'viewer'])
            ->assertUnprocessable();
    }

    public function test_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        $member = User::factory()->create();
        $collection->users()->attach($member, ['role' => CollectionRole::Viewer]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/collections/{$collection->id}/members/{$member->id}")->assertNoContent();

        $this->assertNull($collection->roleFor($member));
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/collections/{$collection->id}/members/{$owner->id}")->assertUnprocessable();
    }

    public function test_a_non_owner_cannot_remove_members(): void
    {
        $owner = User::factory()->create();
        $collection = $this->ownedCollection($owner);
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $collection->users()->attach($editor, ['role' => CollectionRole::Editor]);
        $collection->users()->attach($viewer, ['role' => CollectionRole::Viewer]);
        Sanctum::actingAs($editor);

        $this->deleteJson("/api/collections/{$collection->id}/members/{$viewer->id}")->assertForbidden();
    }
}
