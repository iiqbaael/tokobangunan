<?php

namespace App\Enums;

enum CashMovementType: string
{
    case In  = 'in';
    case Out = 'out';
}