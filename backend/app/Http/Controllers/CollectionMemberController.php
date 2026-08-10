<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Mail\CollectionInviteMail;
use App\Models\Collection;
use App\Models\CollectionInvite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CollectionMemberController extends Controller
{
    public function index(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('view', $collection);

        return response()->json($collection->users()->get());
    }

    /**
     * Adds an existing user directly, or creates a pending invite (and
     * emails a registration link) for an email that isn't registered yet.
     */
    public function store(InviteMemberRequest $request, Collection $collection): JsonResponse
    {
        $email = $request->validated('email');
        $role = $request->validated('role');

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($collection->roleFor($existingUser) !== null) {
                throw ValidationException::withMessages([
                    'email' => 'Diese Person ist bereits Mitglied der Sammlung.',
                ]);
            }

            $collection->users()->attach($existingUser, ['role' => $role]);

            return response()->json($collection->users()->where('users.id', $existingUser->id)->first(), 201);
        }

        $invite = CollectionInvite::pending()
            ->where('collection_id', $collection->id)
            ->where('email', $email)
            ->first();

        $plaintextToken = Str::random(64);

        if ($invite) {
            $invite->update([
                'role' => $role,
                'token_hash' => hash('sha256', $plaintextToken),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ]);
        } else {
            $invite = CollectionInvite::create([
                'collection_id' => $collection->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $plaintextToken),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ]);
        }

        Mail::to($invite->email)->send(new CollectionInviteMail($invite, $plaintextToken));

        return response()->json($invite, 201);
    }

    public function update(UpdateMemberRoleRequest $request, Collection $collection, User $user): JsonResponse
    {
        if ($collection->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'role' => 'Die Rolle des Eigentümers kann nicht geändert werden.',
            ]);
        }

        if ($collection->roleFor($user) === null) {
            abort(404);
        }

        $collection->users()->updateExistingPivot($user->id, ['role' => $request->validated('role')]);

        return response()->json($collection->users()->where('users.id', $user->id)->first());
    }

    public function destroy(Request $request, Collection $collection, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $collection);

        if ($collection->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'Der Eigentümer kann nicht aus der Sammlung entfernt werden.',
            ]);
        }

        $collection->users()->detach($user->id);

        return response()->json(status: 204);
    }
}
