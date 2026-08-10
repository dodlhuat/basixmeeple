<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncGameCategoriesRequest;
use App\Http\Requests\UpdateGameRequest;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function show(Request $request, Game $game): JsonResponse
    {
        abort_unless($game->isVisibleTo($request->user()), 403);

        return response()->json($game->load(['expansions', 'categories']));
    }

    public function update(UpdateGameRequest $request, Game $game): JsonResponse
    {
        $game->update($request->validated());

        return response()->json($game);
    }

    public function destroy(Request $request, Game $game): JsonResponse
    {
        abort_unless($game->isEditableBy($request->user()), 403);

        $game->delete();

        return response()->json(status: 204);
    }

    public function syncCategories(SyncGameCategoriesRequest $request, Game $game): JsonResponse
    {
        $game->categories()->sync($request->validated('category_ids'));

        return response()->json($game->load('categories'));
    }
}
