<?php

namespace App\Models;

use App\Enums\CollectionRole;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'bgg_id',
    'publisher',
    'min_players',
    'max_players',
    'play_time_min',
    'play_time_max',
    'min_age',
    'weight_complexity',
    'description',
    'cover_url',
    'rulebook_path',
    'edition',
    'language',
    'condition_notes',
    'purchase_price',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'weight_complexity' => 'decimal:2',
            'purchase_price' => 'decimal:2',
        ];
    }

    public function expansions(): HasMany
    {
        return $this->hasMany(Expansion::class, 'base_game_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'game_category');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_games')
            ->using(CollectionGame::class)
            ->withPivot(['location', 'condition', 'notes'])
            ->withTimestamps();
    }

    public function playSessions(): HasMany
    {
        return $this->hasMany(PlaySession::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Any member of any collection this game belongs to may view it.
     */
    public function isVisibleTo(User $user): bool
    {
        return $this->collections()
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->exists();
    }

    /**
     * An owner/editor of any collection this game belongs to may edit/delete it,
     * even if that edit also affects other collections sharing the same game.
     */
    public function isEditableBy(User $user): bool
    {
        return $this->collections()
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id)
                ->whereIn('collection_user.role', [CollectionRole::Owner->value, CollectionRole::Editor->value]))
            ->exists();
    }
}
