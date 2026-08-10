<?php

namespace Tests\Feature\Models;

use App\Enums\PlaySessionOutcome;
use App\Models\PlaySession;
use App\Models\SessionPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlaySessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_played_at_is_cast_to_a_datetime_and_outcome_to_the_enum(): void
    {
        $session = PlaySession::factory()->create([
            'played_at' => '2026-01-15 19:30:00',
            'outcome' => PlaySessionOutcome::Win,
        ]);

        $this->assertInstanceOf(Carbon::class, $session->played_at);
        $this->assertSame(PlaySessionOutcome::Win, $session->outcome);
    }

    public function test_players_relation_returns_session_players_in_played_order(): void
    {
        $session = PlaySession::factory()->create();
        $player = SessionPlayer::factory()->create(['session_id' => $session->id]);

        $this->assertTrue($session->players()->first()->is($player));
    }

    public function test_deleting_a_session_cascades_to_its_players(): void
    {
        $session = PlaySession::factory()->create();
        $player = SessionPlayer::factory()->create(['session_id' => $session->id]);

        $session->delete();

        $this->assertDatabaseMissing('session_players', ['id' => $player->id]);
    }

    public function test_a_session_players_user_is_nulled_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $player = SessionPlayer::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertNull($player->fresh()->user_id);
    }
}
