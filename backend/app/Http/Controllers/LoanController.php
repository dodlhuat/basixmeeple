<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\UpdateLoanRequest;
use App\Models\Collection;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request, Collection $collection): JsonResponse
    {
        abort_unless($collection->roleFor($request->user()) !== null, 403);

        $gameIds = $collection->games()->pluck('games.id');

        return response()->json(
            Loan::whereIn('game_id', $gameIds)->orderByDesc('loaned_at')->get(),
        );
    }

    public function store(StoreLoanRequest $request, Collection $collection): JsonResponse
    {
        $loan = Loan::create($request->validated());

        return response()->json($loan, 201);
    }

    public function update(UpdateLoanRequest $request, Collection $collection, Loan $loan): JsonResponse
    {
        abort_unless($loan->game->collections->contains($collection), 404);

        $loan->update($request->validated());

        return response()->json($loan);
    }

    public function destroy(Request $request, Collection $collection, Loan $loan): JsonResponse
    {
        abort_unless($collection->canBeEditedBy($request->user()), 403);
        abort_unless($loan->game->collections->contains($collection), 404);

        $loan->delete();

        return response()->json(status: 204);
    }
}
