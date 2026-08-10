<?php

namespace App\Http\Controllers;

use App\Enums\CollectionRole;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->collections()->get(),
        );
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $collection = DB::transaction(function () use ($request) {
            $collection = Collection::create([
                'name' => $request->validated('name'),
                'owner_id' => $request->user()->id,
            ]);

            $collection->users()->attach($request->user(), ['role' => CollectionRole::Owner]);

            return $collection;
        });

        return response()->json($collection, 201);
    }

    public function show(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('view', $collection);

        return response()->json($collection);
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): JsonResponse
    {
        $collection->update(['name' => $request->validated('name')]);

        return response()->json($collection);
    }

    public function destroy(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('delete', $collection);

        $collection->delete();

        return response()->json(status: 204);
    }
}
