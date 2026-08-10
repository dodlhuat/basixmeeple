<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachGameRequest;
use App\Http\Requests\ImportBggGameRequest;
use App\Http\Requests\StoreGameRequest;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Game;
use App\Services\Bgg\BggClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionGameController extends Controller
{
    private const PIVOT_FIELDS = ['location', 'condition', 'notes'];

    public function __construct(private readonly BggClient $bgg) {}

    public function index(Request $request, Collection $collection): JsonResponse
    {
        abort_unless($collection->roleFor($request->user()) !== null, 403);

        return response()->json($collection->games()->with('categories')->get());
    }

    /**
     * Creates a new game catalog entry and immediately attaches it to this
     * collection with the given pivot fields (location/condition/notes).
     */
    public function store(StoreGameRequest $request, Collection $collection): JsonResponse
    {
        $gameFields = $request->safe()->except(self::PIVOT_FIELDS);
        $pivotFields = $request->safe()->only(self::PIVOT_FIELDS);

        $game = DB::transaction(function () use ($collection, $gameFields, $pivotFields) {
            $game = Game::create($gameFields);
            $collection->games()->attach($game, $pivotFields);

            return $game;
        });

        return response()->json(
            $collection->games()->where('games.id', $game->id)->first(),
            201,
        );
    }

    /**
     * Attaches an already-existing game (e.g. one shared with another
     * collection) to this collection, or updates the pivot fields if it's
     * already attached.
     */
    public function attach(AttachGameRequest $request, Collection $collection, Game $game): JsonResponse
    {
        $pivotFields = $request->validated();

        if ($collection->games()->where('games.id', $game->id)->exists()) {
            $collection->games()->updateExistingPivot($game->id, $pivotFields);
        } else {
            $collection->games()->attach($game, $pivotFields);
        }

        return response()->json($collection->games()->where('games.id', $game->id)->first());
    }

    /**
     * Imports a game from BoardGameGeek by its BGG id and attaches it to
     * this collection. If a game with that bgg_id already exists locally
     * (e.g. shared with another collection), it is reused as-is rather than
     * re-fetched — mirrors the `attach` action above.
     */
    public function importFromBgg(ImportBggGameRequest $request, Collection $collection): JsonResponse
    {
        $bggId = $request->validated('bgg_id');
        $pivotFields = $request->safe()->only(self::PIVOT_FIELDS);

        $game = DB::transaction(function () use ($bggId, $pivotFields, $collection) {
            $game = Game::where('bgg_id', $bggId)->first();

            if (! $game) {
                $data = $this->bgg->find($bggId);
                $game = Game::create($data->toGameAttributes());

                $categoryIds = collect($data->categories)
                    ->map(fn (string $name) => Category::firstOrCreate(['name' => $name])->id);

                $game->categories()->sync($categoryIds);
            }

            if ($collection->games()->where('games.id', $game->id)->exists()) {
                $collection->games()->updateExistingPivot($game->id, $pivotFields);
            } else {
                $collection->games()->attach($game, $pivotFields);
            }

            return $game;
        });

        return response()->json(
            $collection->games()->with('categories')->where('games.id', $game->id)->first(),
            201,
        );
    }

    /**
     * Detaches the game from this collection only; the game record itself
     * (and its presence in other collections) is untouched.
     */
    public function destroy(Request $request, Collection $collection, Game $game): JsonResponse
    {
        abort_unless($collection->canBeEditedBy($request->user()), 403);

        $collection->games()->detach($game->id);

        return response()->json(status: 204);
    }
}
