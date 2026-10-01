<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash     = 'cash';
    case Transfer = 'transfer';
    case Ewallet  = 'ewallet';
    case Card     = 'card';
    case Credit   = 'credit';
}