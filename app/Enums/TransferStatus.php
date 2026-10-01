<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Draft     = 'draft';
    case Sent      = 'sent';
    case Partial   = 'partial';
    case Received  = 'received';
    case Cancelled = 'cancelled';
}