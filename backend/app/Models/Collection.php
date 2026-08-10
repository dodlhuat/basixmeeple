<?php

namespace App\Models;

use App\Enums\CollectionRole;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'owner_id'])]
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'collection_user')
            ->using(CollectionUser::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'collection_games')
            ->using(CollectionGame::class)
            ->withPivot(['location', 'condition', 'notes'])
            ->withTimestamps();
    }

    public function playSessions(): HasMany
    {
        return $this->hasMany(PlaySession::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(CollectionInvite::class);
    }

    public function roleFor(User $user): ?CollectionRole
    {
        $membership = $this->users()->where('users.id', $user->id)->first();

        return $membership?->pivot->role;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->roleFor($user)?->canWrite() ?? false;
    }
}
