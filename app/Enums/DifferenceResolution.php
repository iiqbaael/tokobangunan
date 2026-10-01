<?php

namespace App\Enums;

enum DifferenceResolution: string
{
    case None       = 'none';
    case Returned   = 'returned';
    case WrittenOff = 'written_off';
}