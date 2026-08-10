<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpansionRequest;
use App\Http\Requests\UpdateExpansionRequest;
use App\Models\Expansion;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpansionController extends Controller
{
    public function index(Request $request, Game $game): JsonResponse
    {
        abort_unless($game->isVisibleTo($request->user()), 403);

        return response()->json($game->expansions);
    }

    public function store(StoreExpansionRequest $request, Game $game): JsonResponse
    {
        $expansion = $game->expansions()->create($request->validated());

        return response()->json($expansion, 201);
    }

    public function update(UpdateExpansionRequest $request, Game $game, Expansion $expansion): JsonResponse
    {
        abort_if($expansion->base_game_id !== $game->id, 404);

        $expansion->update($request->validated());

        return response()->json($expansion);
    }

    public function destroy(Request $request, Game $game, Expansion $expansion): JsonResponse
    {
        abort_unless($game->isEditableBy($request->user()), 403);
        abort_if($expansion->base_game_id !== $game->id, 404);

        $expansion->delete();

        return response()->json(status: 204);
    }
}
