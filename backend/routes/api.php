<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BggController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionGameController;
use App\Http\Controllers\CollectionInviteController;
use App\Http\Controllers\CollectionMemberController;
use App\Http\Controllers\ExpansionController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PlaySessionController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\WishlistItemController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('collections', CollectionController::class);

    Route::get('/collections/{collection}/members', [CollectionMemberController::class, 'index']);
    Route::post('/collections/{collection}/members', [CollectionMemberController::class, 'store']);
    Route::patch('/collections/{collection}/members/{user}', [CollectionMemberController::class, 'update']);
    Route::delete('/collections/{collection}/members/{user}', [CollectionMemberController::class, 'destroy']);

    Route::get('/collections/{collection}/invites', [CollectionInviteController::class, 'index']);
    Route::delete('/collections/{collection}/invites/{invite}', [CollectionInviteController::class, 'destroy']);

    Route::get('/bgg/search', [BggController::class, 'search']);

    Route::get('/collections/{collection}/games', [CollectionGameController::class, 'index']);
    Route::post('/collections/{collection}/games', [CollectionGameController::class, 'store']);
    Route::post('/collections/{collection}/games/{game}/attach', [CollectionGameController::class, 'attach']);
    Route::post('/collections/{collection}/games/import-bgg', [CollectionGameController::class, 'importFromBgg']);
    Route::delete('/collections/{collection}/games/{game}', [CollectionGameController::class, 'destroy']);

    Route::get('/games/{game}', [GameController::class, 'show']);
    Route::patch('/games/{game}', [GameController::class, 'update']);
    Route::delete('/games/{game}', [GameController::class, 'destroy']);
    Route::put('/games/{game}/categories', [GameController::class, 'syncCategories']);

    Route::get('/games/{game}/expansions', [ExpansionController::class, 'index']);
    Route::post('/games/{game}/expansions', [ExpansionController::class, 'store']);
    Route::patch('/games/{game}/expansions/{expansion}', [ExpansionController::class, 'update']);
    Route::delete('/games/{game}/expansions/{expansion}', [ExpansionController::class, 'destroy']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('/collections/{collection}/play-sessions', [PlaySessionController::class, 'index']);
    Route::get('/collections/{collection}/play-sessions/{playSession}', [PlaySessionController::class, 'show']);
    Route::post('/collections/{collection}/play-sessions', [PlaySessionController::class, 'store']);
    Route::patch('/collections/{collection}/play-sessions/{playSession}', [PlaySessionController::class, 'update']);
    Route::delete('/collections/{collection}/play-sessions/{playSession}', [PlaySessionController::class, 'destroy']);

    Route::get('/collections/{collection}/loans', [LoanController::class, 'index']);
    Route::post('/collections/{collection}/loans', [LoanController::class, 'store']);
    Route::patch('/collections/{collection}/loans/{loan}', [LoanController::class, 'update']);
    Route::delete('/collections/{collection}/loans/{loan}', [LoanController::class, 'destroy']);

    Route::get('/collections/{collection}/wishlist-items', [WishlistItemController::class, 'index']);
    Route::post('/collections/{collection}/wishlist-items', [WishlistItemController::class, 'store']);
    Route::patch('/collections/{collection}/wishlist-items/{wishlistItem}', [WishlistItemController::class, 'update']);
    Route::delete('/collections/{collection}/wishlist-items/{wishlistItem}', [WishlistItemController::class, 'destroy']);

    Route::post('/sync', [SyncController::class, 'sync']);
});
