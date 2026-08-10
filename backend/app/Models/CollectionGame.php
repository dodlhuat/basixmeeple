<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CollectionGame extends Pivot
{
    use HasUuids;

    // AsPivot::getTable() would otherwise derive "collection_game" (singular)
    // from the class name, which doesn't exist — only correct when the pivot
    // is accessed through the collection/game relations, which pass the
    // table name explicitly and bypass this. Explicit here so direct usage
    // (e.g. CollectionGame::find()) resolves to the real table too.
    protected $table = 'collection_games';

    protected $keyType = 'string';
}
