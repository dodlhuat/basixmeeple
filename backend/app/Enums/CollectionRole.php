<?php

namespace App\Enums;

enum CollectionRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }
}
