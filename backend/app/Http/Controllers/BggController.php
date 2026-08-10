<?php

namespace App\Http\Controllers;

use App\Http\Requests\BggSearchRequest;
use App\Services\Bgg\BggClient;
use Illuminate\Http\JsonResponse;

class BggController extends Controller
{
    public function __construct(private readonly BggClient $bgg) {}

    public function search(BggSearchRequest $request): JsonResponse
    {
        return response()->json($this->bgg->search($request->validated('q')));
    }
}
