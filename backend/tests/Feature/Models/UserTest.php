<?php

namespace Tests\Feature\Models;

use App\Models\Collection;
use App\Models\Loan;
use App\Models\SessionPlayer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_and_remember_token_are_hidden_from_array_and_json(): void
    {
        $user = User::factory()->create();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create(['password' => 'plain-text-password']);

        $this->assertTrue(Hash::check('plain-text-password', $user->password));
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'spieler@example.com']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'spieler@example.com']);
    }

    public function test_owned_collections_relation(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($user->ownedCollections()->first()->is($collection));
    }

    public function test_session_appearances_and_loans_as_borrower_relations(): void
    {
        $user = User::factory()->create();
        $player = SessionPlayer::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create(['borrower_user_id' => $user->id]);

        $this->assertTrue($user->sessionAppearances()->first()->is($player));
        $this->assertTrue($user->loansAsBorrower()->first()->is($loan));
    }
}
