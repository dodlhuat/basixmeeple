<?php

namespace App\Enums;

enum PlaySessionOutcome: string
{
    case Win = 'win';
    case Loss = 'loss';
    case Draw = 'draw';
}
