<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncRequest;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionGame;
use App\Models\Expansion;
use App\Models\Game;
use App\Models\Loan;
use App\Models\PlaySession;
use App\Models\SessionPlayer;
use App\Models\User;
use App\Models\WishlistItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * Mass-assignable fields per syncable entity, mirroring each model's
     * #[Fillable] attribute. `id` is deliberately never listed here — it is
     * force-filled separately so the client's UUID is preserved instead of
     * being silently dropped by the Fillable guard (see [[basixmeeple-project]]
     * memory: Fillable drops unlisted keys with no exception).
     */
    private const FILLABLE = [
        'games' => ['title', 'bgg_id', 'publisher', 'min_players', 'max_players', 'play_time_min', 'play_time_max', 'min_age', 'weight_complexity', 'description', 'cover_url', 'rulebook_path', 'edition', 'language', 'condition_notes', 'purchase_price'],
        'expansions' => ['base_game_id', 'title', 'bgg_id', 'cover_url'],
        'categories' => ['name'],
        'collection_games' => ['collection_id', 'game_id', 'location', 'condition', 'notes'],
        'play_sessions' => ['game_id', 'collection_id', 'played_at', 'duration_min', 'outcome', 'notes'],
        'session_players' => ['session_id', 'user_id', 'player_name', 'is_winner', 'score'],
        'loans' => ['game_id', 'borrower_name', 'borrower_user_id', 'loaned_at', 'due_date', 'returned_at'],
        'wishlist_items' => ['collection_id', 'title', 'bgg_id', 'priority', 'price_estimate'],
    ];

    /**
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'games' => Game::class,
        'expansions' => Expansion::class,
        'categories' => Category::class,
        'collection_games' => CollectionGame::class,
        'play_sessions' => PlaySession::class,
        'session_players' => SessionPlayer::class,
        'loans' => Loan::class,
        'wishlist_items' => WishlistItem::class,
    ];

    public function sync(SyncRequest $request): JsonResponse
    {
        $user = $request->user();

        $results = array_map(
            fn (array $op) => $this->applyOperation($user, $op),
            $request->validated('operations'),
        );

        return response()->json([
            'results' => $results,
            'snapshot' => $this->buildSnapshot($user),
        ]);
    }

    /**
     * @param  array{entity: string, entity_id: string, operation: string, payload: array<string, mixed>|null, queued_at: string}  $op
     * @return array{entity: string, entity_id: string, status: string, reason?: string}
     */
    private function applyOperation(User $user, array $op): array
    {
        $entity = $op['entity'];
        $id = $op['entity_id'];
        $payload = $op['payload'] ?? [];

        $modelClass = self::MODELS[$entity];
        $existing = $modelClass::find($id);

        if ($op['operation'] === 'delete') {
            if (! $existing) {
                return $this->result($entity, $id, 'applied');
            }

            if (! $this->canWrite($user, $entity, $existing, $payload)) {
                return $this->result($entity, $id, 'rejected', 'Keine Berechtigung.');
            }

            $existing->delete();

            return $this->result($entity, $id, 'applied');
        }

        if (! $this->canWrite($user, $entity, $existing, $payload)) {
            return $this->result($entity, $id, 'rejected', 'Keine Berechtigung.');
        }

        if ($existing && ! $this->isNewer($existing, $payload)) {
            return $this->result($entity, $id, 'skipped', 'Server-Stand ist neuer.');
        }

        $model = $existing ?? new $modelClass;
        $model->forceFill(Arr::only($payload, self::FILLABLE[$entity]) + ['id' => $id]);
        $model->save();

        return $this->result($entity, $id, 'applied');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canWrite(User $user, string $entity, ?Model $existing, array $payload): bool
    {
        return match ($entity) {
            'games' => $existing ? $existing->isEditableBy($user) : true,
            'expansions' => $this->gameEditable($user, $existing?->base_game_id ?? $payload['base_game_id'] ?? null),
            'categories' => true,
            'collection_games' => $this->collectionEditable($user, $existing?->collection_id ?? $payload['collection_id'] ?? null),
            'play_sessions' => $this->collectionEditable($user, $existing?->collection_id ?? $payload['collection_id'] ?? null),
            'wishlist_items' => $this->collectionEditable($user, $existing?->collection_id ?? $payload['collection_id'] ?? null),
            'session_players' => $this->sessionPlayerEditable($user, $existing, $payload),
            'loans' => $this->gameEditable($user, $existing?->game_id ?? $payload['game_id'] ?? null),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sessionPlayerEditable(User $user, ?Model $existing, array $payload): bool
    {
        $sessionId = $existing?->session_id ?? $payload['session_id'] ?? null;

        if (! $sessionId) {
            return false;
        }

        $session = PlaySession::find($sessionId);

        return $session && $this->collectionEditable($user, $session->collection_id);
    }

    /**
     * Games/expansions/loans have no direct collection_id; a user may write
     * them if they're an editor/owner of any collection the game belongs to
     * (same rule as Game::isEditableBy(), reused here since loans in
     * particular have no collection scope of their own to check instead).
     */
    private function gameEditable(User $user, ?string $gameId): bool
    {
        if (! $gameId) {
            return false;
        }

        $game = Game::find($gameId);

        return $game?->isEditableBy($user) ?? false;
    }

    private function collectionEditable(User $user, ?string $collectionId): bool
    {
        if (! $collectionId) {
            return false;
        }

        $collection = Collection::find($collectionId);

        return $collection?->canBeEditedBy($user) ?? false;
    }

    /**
     * Last-write-wins: the queued client edit only applies if it happened
     * after the server's current version of the record.
     *
     * @param  array<string, mixed>  $payload
     */
    private function isNewer(Model $existing, array $payload): bool
    {
        if (! isset($payload['updated_at'])) {
            return false;
        }

        return Carbon::parse($payload['updated_at'])->greaterThan($existing->updated_at);
    }

    /**
     * @return array{entity: string, entity_id: string, status: string, reason?: string}
     */
    private function result(string $entity, string $id, string $status, ?string $reason = null): array
    {
        $result = ['entity' => $entity, 'entity_id' => $id, 'status' => $status];

        if ($reason !== null) {
            $result['reason'] = $reason;
        }

        return $result;
    }

    /**
     * Full current-state snapshot of everything the user can see, across all
     * collections they belong to. The client wipes and rebuilds its local
     * Dexie mirror from this on every sync — chosen over incremental/tombstone
     * sync since the expected data volume (private board game collections)
     * is small and this avoids needing a deletion log for hard deletes.
     *
     * @return array<string, mixed>
     */
    private function buildSnapshot(User $user): array
    {
        $collections = $user->collections()->get();
        $collectionIds = $collections->pluck('id');

        $collectionUserRows = DB::table('collection_user')->whereIn('collection_id', $collectionIds)->get();
        $userIds = $collectionUserRows->pluck('user_id')->unique()->values();
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'email']);

        $games = Game::whereHas('collections', fn ($query) => $query->whereIn('collections.id', $collectionIds))->get();
        $gameIds = $games->pluck('id');

        $expansions = Expansion::whereIn('base_game_id', $gameIds)->get();
        $categories = Category::all();
        $gameCategoryRows = DB::table('game_category')->whereIn('game_id', $gameIds)->get();
        $collectionGameRows = DB::table('collection_games')->whereIn('collection_id', $collectionIds)->get();

        $playSessions = PlaySession::whereIn('collection_id', $collectionIds)->get();
        $sessionPlayers = SessionPlayer::whereIn('session_id', $playSessions->pluck('id'))->get();

        $loans = Loan::whereIn('game_id', $gameIds)->get();
        $wishlistItems = WishlistItem::whereIn('collection_id', $collectionIds)->get();

        return [
            'users' => $users->values(),
            'collections' => $collections,
            'collection_user' => $collectionUserRows,
            'games' => $games,
            'expansions' => $expansions,
            'categories' => $categories,
            'game_category' => $gameCategoryRows,
            'collection_games' => $collectionGameRows,
            'play_sessions' => $playSessions,
            'session_players' => $sessionPlayers,
            'loans' => $loans,
            'wishlist_items' => $wishlistItems,
        ];
    }
}
