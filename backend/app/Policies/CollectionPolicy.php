<?php

namespace App\Policies;

use App\Enums\CollectionRole;
use App\Models\Collection;
use App\Models\User;

class CollectionPolicy
{
    /**
     * Any member (owner/editor/viewer) can view the collection.
     */
    public function view(User $user, Collection $collection): bool
    {
        return $collection->roleFor($user) !== null;
    }

    /**
     * Any authenticated user can create a collection (they become its owner).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner can rename the collection.
     */
    public function update(User $user, Collection $collection): bool
    {
        return $collection->roleFor($user) === CollectionRole::Owner;
    }

    /**
     * Only the owner can delete the collection.
     */
    public function delete(User $user, Collection $collection): bool
    {
        return $collection->roleFor($user) === CollectionRole::Owner;
    }

    /**
     * Only the owner can invite/remove members, change roles, or revoke invites.
     */
    public function manageMembers(User $user, Collection $collection): bool
    {
        return $collection->roleFor($user) === CollectionRole::Owner;
    }
}
