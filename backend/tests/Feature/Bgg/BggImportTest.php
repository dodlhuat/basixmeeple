<?php

namespace Tests\Feature\Bgg;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BggImportTest extends TestCase
{
    use RefreshDatabase;

    private const SEARCH_XML = <<<'XML'
        <?xml version="1.0" encoding="utf-8"?>
        <items total="2" termsofuse="https://boardgamegeek.com/xmlapi/termsofuse">
        <item type="boardgame" id="224517">
        <name type="primary" value="Brass: Birmingham" />
        <yearpublished value="2018" />
        </item>
        <item type="boardgame" id="12333">
        <name type="primary" value="Twilight Struggle" />
        <yearpublished value="2005" />
        </item>
        </items>
        XML;

    private const THING_XML = <<<'XML'
        <?xml version="1.0" encoding="utf-8"?>
        <items termsofuse="https://boardgamegeek.com/xmlapi/termsofuse">
        <item type="boardgame" id="224517">
        <thumbnail>https://cf.geekdo-images.com/thumb.jpg</thumbnail>
        <image>https://cf.geekdo-images.com/image.jpg</image>
        <name type="primary" sortindex="1" value="Brass: Birmingham" />
        <name type="alternate" sortindex="1" value="Brass Birmingham (DE)" />
        <description>Build networks, grow industries &amp;amp; navigate the Industrial Revolution.</description>
        <yearpublished value="2018" />
        <minplayers value="2" />
        <maxplayers value="4" />
        <minplaytime value="60" />
        <maxplaytime value="120" />
        <minage value="14" />
        <link type="boardgamecategory" id="1021" value="Economic" />
        <link type="boardgamecategory" id="1026" value="Post-Napoleonic" />
        <link type="boardgamemechanic" id="2004" value="Hand Management" />
        <link type="boardgamepublisher" id="1234" value="Roxley" />
        <statistics page="1">
        <ratings>
        <averageweight value="3.9013" />
        </ratings>
        </statistics>
        </item>
        </items>
        XML;

    private const NOT_FOUND_XML = <<<'XML'
        <?xml version="1.0" encoding="utf-8"?>
        <items termsofuse="https://boardgamegeek.com/xmlapi/termsofuse" />
        XML;

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

    public function test_search_proxies_bgg_and_returns_matches(): void
    {
        Http::fake(['*/search*' => Http::response(self::SEARCH_XML)]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/bgg/search?q=Brass');

        $response->assertOk()->assertJsonCount(2);
        $response->assertJsonPath('0.bgg_id', 224517);
        $response->assertJsonPath('0.title', 'Brass: Birmingham');
    }

    public function test_an_editor_can_import_a_new_game_and_it_is_attached_with_categories(): void
    {
        Http::fake(['*/thing*' => Http::response(self::THING_XML)]);
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/collections/{$collection->id}/games/import-bgg", [
            'bgg_id' => 224517,
            'location' => 'Regal B',
        ]);

        $response->assertCreated();
        $game = Game::where('bgg_id', 224517)->firstOrFail();
        $this->assertSame('Brass: Birmingham', $game->title);
        $this->assertSame('Build networks, grow industries & navigate the Industrial Revolution.', $game->description);
        $this->assertSame('Roxley', $game->publisher);
        $this->assertEqualsWithDelta(3.9013, (float) $game->weight_complexity, 0.01);
        $this->assertSame(['Economic', 'Post-Napoleonic'], $game->categories()->pluck('name')->all());
        $this->assertTrue($collection->games()->where('games.id', $game->id)->exists());
        $this->assertSame('Regal B', $collection->games()->where('games.id', $game->id)->first()->pivot->location);
    }

    public function test_importing_a_known_bgg_id_reuses_the_existing_local_game(): void
    {
        Http::fake(['*/thing*' => Http::response(self::THING_XML)]);
        $existingGame = Game::factory()->create(['bgg_id' => 224517]);
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $this->postJson("/api/collections/{$collection->id}/games/import-bgg", ['bgg_id' => 224517])
            ->assertCreated();

        Http::assertNothingSent();
        $this->assertSame(1, Game::where('bgg_id', 224517)->count());
        $this->assertTrue($collection->games()->where('games.id', $existingGame->id)->exists());
    }

    public function test_a_viewer_cannot_import_a_game(): void
    {
        $viewer = User::factory()->create();
        $collection = $this->collectionWithMember($viewer, CollectionRole::Viewer);
        Sanctum::actingAs($viewer);

        $this->postJson("/api/collections/{$collection->id}/games/import-bgg", ['bgg_id' => 224517])
            ->assertForbidden();
    }

    public function test_importing_an_unknown_bgg_id_returns_404(): void
    {
        Http::fake(['*/thing*' => Http::response(self::NOT_FOUND_XML)]);
        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        Sanctum::actingAs($editor);

        $this->postJson("/api/collections/{$collection->id}/games/import-bgg", ['bgg_id' => 999999999])
            ->assertNotFound();
    }
}
