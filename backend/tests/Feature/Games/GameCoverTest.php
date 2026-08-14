<?php

namespace Tests\Feature\Games;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameCoverTest extends TestCase
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

    public function test_an_editor_can_upload_a_cover_image(): void
    {
        Storage::fake('public');

        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create(['cover_url' => null]);
        $collection->games()->attach($game);
        Sanctum::actingAs($editor);

        $response = $this->postJson("/api/games/{$game->id}/cover", [
            'cover' => UploadedFile::fake()->image('box.jpg', 400, 400)->size(1024),
        ]);

        $response->assertOk();
        $game->refresh();
        $this->assertNotNull($game->cover_url);
        $this->assertStringContainsString('/api/storage/covers/', $game->cover_url);

        $path = 'covers/'.basename(parse_url($game->cover_url, PHP_URL_PATH));
        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_a_new_cover_deletes_the_previously_stored_file(): void
    {
        Storage::fake('public');

        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create(['cover_url' => null]);
        $collection->games()->attach($game);
        Sanctum::actingAs($editor);

        $this->postJson("/api/games/{$game->id}/cover", [
            'cover' => UploadedFile::fake()->image('first.jpg', 200, 200),
        ])->assertOk();
        $firstPath = 'covers/'.basename(parse_url($game->refresh()->cover_url, PHP_URL_PATH));

        $this->postJson("/api/games/{$game->id}/cover", [
            'cover' => UploadedFile::fake()->image('second.jpg', 200, 200),
        ])->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_an_external_bgg_cover_url_is_not_deleted_from_storage_on_replace(): void
    {
        Storage::fake('public');

        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create(['cover_url' => 'https://boardgamegeek.com/image/example.jpg']);
        $collection->games()->attach($game);
        Sanctum::actingAs($editor);

        $this->postJson("/api/games/{$game->id}/cover", [
            'cover' => UploadedFile::fake()->image('box.jpg', 200, 200),
        ])->assertOk();

        $this->assertStringContainsString('/api/storage/covers/', $game->refresh()->cover_url);
    }

    public function test_a_viewer_cannot_upload_a_cover_image(): void
    {
        Storage::fake('public');

        $viewer = User::factory()->create();
        $collection = $this->collectionWithMember($viewer, CollectionRole::Viewer);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs($viewer);

        $this->postJson("/api/games/{$game->id}/cover", [
            'cover' => UploadedFile::fake()->image('box.jpg', 200, 200),
        ])->assertForbidden();
    }

    public function test_uploading_a_non_image_file_is_rejected(): void
    {
        Storage::fake('public');

        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs($editor);

        $this->postJson("/api/games/{$game->id}/cover", [
            'cover' => UploadedFile::fake()->create('rules.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable();
    }

    public function test_an_editor_can_delete_a_cover_image(): void
    {
        Storage::fake('public');

        $editor = User::factory()->create();
        $collection = $this->collectionWithMember($editor, CollectionRole::Editor);
        $game = Game::factory()->create();
        $collection->games()->attach($game);
        Sanctum::actingAs($editor);

        $this->postJson("/api/games/{$game->id}/cover", [
            'cover' => UploadedFile::fake()->image('box.jpg', 200, 200),
        ])->assertOk();
        $path = 'covers/'.basename(parse_url($game->refresh()->cover_url, PHP_URL_PATH));

        $this->deleteJson("/api/games/{$game->id}/cover")->assertOk()->assertJsonPath('cover_url', null);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_stored_cover_is_publicly_reachable_without_auth(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('covers/example.jpg', 'fake-image-bytes');

        $this->get('/api/storage/covers/example.jpg')->assertOk();
    }

    public function test_a_missing_stored_cover_returns_404(): void
    {
        Storage::fake('public');

        $this->get('/api/storage/covers/does-not-exist.jpg')->assertNotFound();
    }
}
