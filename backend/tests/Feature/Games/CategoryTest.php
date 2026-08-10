<?php

namespace Tests\Feature\Games;

use App\Enums\CollectionRole;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_list_categories(): void
    {
        Category::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(3);
    }

    public function test_any_authenticated_user_can_create_a_category(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/categories', ['name' => 'Strategie'])
            ->assertCreated()
            ->assertJsonPath('name', 'Strategie');
    }

    public function test_duplicate_category_names_are_rejected(): void
    {
        Category::factory()->create(['name' => 'Strategie']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/categories', ['name' => 'Strategie'])->assertUnprocessable();
    }

    public function test_an_editor_can_sync_categories_onto_a_game(): void
    {
        $editor = User::factory()->create();
        $collection = Collection::factory()->create(['owner_id' => $editor->id]);
        $collection->users()->attach($editor, ['role' => CollectionRole::Owner]);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        $categories = Category::factory()->count(2)->create();
        Sanctum::actingAs($editor);

        $response = $this->putJson("/api/games/{$game->id}/categories", [
            'category_ids' => $categories->pluck('id')->all(),
        ]);

        $response->assertOk();
        $this->assertSame(2, $game->categories()->count());
    }

    public function test_a_viewer_cannot_sync_categories_onto_a_game(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $collection = Collection::factory()->create(['owner_id' => $owner->id]);
        $collection->users()->attach($owner, ['role' => CollectionRole::Owner]);
        $collection->users()->attach($viewer, ['role' => CollectionRole::Viewer]);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs($viewer);

        $this->putJson("/api/games/{$game->id}/categories", ['category_ids' => []])->assertForbidden();
    }
}
