<?php

namespace Tests\Feature\Auth;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\CollectionInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_with_a_valid_token_creates_the_user_and_resolves_all_pending_invites_for_that_email(): void
    {
        $email = 'neu@example.com';
        $collectionOne = Collection::factory()->create();
        $collectionTwo = Collection::factory()->create();

        $inviteOne = CollectionInvite::factory()
            ->withToken('token-one')
            ->create(['collection_id' => $collectionOne->id, 'email' => $email, 'role' => CollectionRole::Viewer]);
        $inviteTwo = CollectionInvite::factory()
            ->withToken('token-two')
            ->create(['collection_id' => $collectionTwo->id, 'email' => $email, 'role' => CollectionRole::Editor]);

        $response = $this->postJson('/api/register', [
            'token' => 'token-one',
            'name' => 'Neue Person',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.email', $email);
        $this->assertArrayHasKey('token', $response->json());

        $user = User::where('email', $email)->firstOrFail();

        $this->assertSame(CollectionRole::Viewer, $collectionOne->roleFor($user));
        $this->assertSame(CollectionRole::Editor, $collectionTwo->roleFor($user));
        $this->assertNotNull($inviteOne->fresh()->accepted_at);
        $this->assertNotNull($inviteTwo->fresh()->accepted_at);
    }

    public function test_registering_with_an_unknown_token_fails(): void
    {
        $response = $this->postJson('/api/register', [
            'token' => 'does-not-exist',
            'name' => 'Neue Person',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('token');
    }

    public function test_registering_with_an_expired_token_fails(): void
    {
        CollectionInvite::factory()->expired()->withToken('expired-token')->create();

        $response = $this->postJson('/api/register', [
            'token' => 'expired-token',
            'name' => 'Neue Person',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('token');
    }

    public function test_registering_with_an_already_accepted_token_fails(): void
    {
        CollectionInvite::factory()->accepted()->withToken('used-token')->create();

        $response = $this->postJson('/api/register', [
            'token' => 'used-token',
            'name' => 'Neue Person',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('token');
    }
}
