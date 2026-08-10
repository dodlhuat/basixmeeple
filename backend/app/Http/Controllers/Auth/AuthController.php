<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\CollectionInvite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registration only ever happens via a collection invite token. On
     * success, every pending invite for the invite's email (not just the one
     * whose token was used) is resolved into collection membership, so a
     * user invited to several collections before signing up doesn't have to
     * register multiple times.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tokenHash = hash('sha256', $data['token']);

        $invite = CollectionInvite::pending()->where('token_hash', $tokenHash)->first();

        if (! $invite) {
            throw ValidationException::withMessages([
                'token' => 'Diese Einladung ist ungültig oder abgelaufen.',
            ]);
        }

        $user = DB::transaction(function () use ($data, $invite) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $invite->email,
                'password' => $data['password'],
            ]);

            $pendingInvites = CollectionInvite::pending()->where('email', $invite->email)->get();

            foreach ($pendingInvites as $pendingInvite) {
                $pendingInvite->collection->users()->attach($user, ['role' => $pendingInvite->role]);
                $pendingInvite->update(['accepted_at' => now()]);
            }

            return $user;
        });

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.',
            ]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
