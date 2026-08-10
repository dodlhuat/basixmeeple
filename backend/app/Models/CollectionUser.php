<?php

namespace App\Models;

use App\Enums\CollectionRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CollectionUser extends Pivot
{
    protected function casts(): array
    {
        return [
            'role' => CollectionRole::class,
        ];
    }
}
