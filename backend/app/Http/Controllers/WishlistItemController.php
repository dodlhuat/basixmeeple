<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWishlistItemRequest;
use App\Http\Requests\UpdateWishlistItemRequest;
use App\Models\Collection;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistItemController extends Controller
{
    public function index(Request $request, Collection $collection): JsonResponse
    {
        abort_unless($collection->roleFor($request->user()) !== null, 403);

        return response()->json($collection->wishlistItems()->orderByDesc('priority')->get());
    }

    public function store(StoreWishlistItemRequest $request, Collection $collection): JsonResponse
    {
        $wishlistItem = $collection->wishlistItems()->create($request->validated());

        return response()->json($wishlistItem, 201);
    }

    public function update(UpdateWishlistItemRequest $request, Collection $collection, WishlistItem $wishlistItem): JsonResponse
    {
        abort_if($wishlistItem->collection_id !== $collection->id, 404);

        $wishlistItem->update($request->validated());

        return response()->json($wishlistItem);
    }

    public function destroy(Request $request, Collection $collection, WishlistItem $wishlistItem): JsonResponse
    {
        abort_unless($collection->canBeEditedBy($request->user()), 403);
        abort_if($wishlistItem->collection_id !== $collection->id, 404);

        $wishlistItem->delete();

        return response()->json(status: 204);
    }
}
