<?php

namespace App\Models;

use Database\Factories\ExpansionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['base_game_id', 'title', 'bgg_id', 'cover_url'])]
class Expansion extends Model
{
    /** @use HasFactory<ExpansionFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function baseGame(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'base_game_id');
    }
}
