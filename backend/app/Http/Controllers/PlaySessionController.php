<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlaySessionRequest;
use App\Http\Requests\UpdatePlaySessionRequest;
use App\Models\Collection;
use App\Models\PlaySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaySessionController extends Controller
{
    public function index(Request $request, Collection $collection): JsonResponse
    {
        abort_unless($collection->roleFor($request->user()) !== null, 403);

        return response()->json(
            $collection->playSessions()->with('players')->orderByDesc('played_at')->get(),
        );
    }

    public function show(Request $request, Collection $collection, PlaySession $playSession): JsonResponse
    {
        abort_unless($collection->roleFor($request->user()) !== null, 403);
        abort_if($playSession->collection_id !== $collection->id, 404);

        return response()->json($playSession->load('players'));
    }

    public function store(StorePlaySessionRequest $request, Collection $collection): JsonResponse
    {
        $playSession = DB::transaction(function () use ($request, $collection) {
            $playSession = $collection->playSessions()->create($request->safe()->except('players'));

            foreach ($request->validated('players') as $player) {
                $playSession->players()->create($player);
            }

            return $playSession;
        });

        return response()->json($playSession->load('players'), 201);
    }

    public function update(UpdatePlaySessionRequest $request, Collection $collection, PlaySession $playSession): JsonResponse
    {
        abort_if($playSession->collection_id !== $collection->id, 404);

        DB::transaction(function () use ($request, $playSession) {
            $playSession->update($request->safe()->except('players'));

            if ($request->has('players')) {
                $playSession->players()->delete();

                foreach ($request->validated('players') as $player) {
                    $playSession->players()->create($player);
                }
            }
        });

        return response()->json($playSession->load('players'));
    }

    public function destroy(Request $request, Collection $collection, PlaySession $playSession): JsonResponse
    {
        abort_unless($collection->canBeEditedBy($request->user()), 403);
        abort_if($playSession->collection_id !== $collection->id, 404);

        $playSession->delete();

        return response()->json(status: 204);
    }
}
