<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\CollectionInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionInviteController extends Controller
{
    public function index(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('manageMembers', $collection);

        return response()->json($collection->invites()->pending()->get());
    }

    public function destroy(Request $request, Collection $collection, CollectionInvite $invite): JsonResponse
    {
        $this->authorize('manageMembers', $collection);

        abort_if($invite->collection_id !== $collection->id, 404);

        $invite->delete();

        return response()->json(status: 204);
    }
}
