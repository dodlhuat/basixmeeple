<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Expansion;
use App\Models\Game;
use App\Models\Loan;
use App\Models\PlaySession;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_weight_complexity_and_purchase_price_are_cast_to_decimal_strings(): void
    {
        $game = Game::factory()->create([
            'weight_complexity' => 3.5,
            'purchase_price' => 42.5,
        ]);

        $this->assertSame('3.50', $game->weight_complexity);
        $this->assertSame('42.50', $game->purchase_price);
    }

    public function test_bgg_id_must_be_unique(): void
    {
        Game::factory()->create(['bgg_id' => 12345]);

        $this->expectException(QueryException::class);

        Game::factory()->create(['bgg_id' => 12345]);
    }

    public function test_deleting_a_game_cascades_to_its_expansions(): void
    {
        $game = Game::factory()->create();
        $expansion = Expansion::factory()->create(['base_game_id' => $game->id]);

        $game->delete();

        $this->assertDatabaseMissing('expansions', ['id' => $expansion->id]);
    }

    public function test_deleting_a_game_cascades_to_its_loans_and_play_sessions(): void
    {
        $game = Game::factory()->create();
        $loan = Loan::factory()->create(['game_id' => $game->id]);
        $session = PlaySession::factory()->create(['game_id' => $game->id]);

        $game->delete();

        $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
        $this->assertDatabaseMissing('play_sessions', ['id' => $session->id]);
    }

    public function test_categories_relation_is_a_many_to_many(): void
    {
        $game = Game::factory()->create();
        $category = Category::factory()->create();

        $game->categories()->attach($category);

        $this->assertTrue($game->categories()->first()->is($category));
        $this->assertTrue($category->games()->first()->is($game));
    }

    public function test_collections_relation_exposes_pivot_attributes(): void
    {
        $game = Game::factory()->create();
        $collection = Collection::factory()->create();

        $collection->games()->attach($game, [
            'location' => 'Regal 3',
            'condition' => 'wie neu',
            'notes' => 'Erstauflage',
        ]);

        $pivot = $game->collections()->first()->pivot;

        $this->assertSame('Regal 3', $pivot->location);
        $this->assertSame('wie neu', $pivot->condition);
        $this->assertSame('Erstauflage', $pivot->notes);
    }
}
