<?php

namespace App\Enums;

enum StockMutationType: string
{
    case SaleOut       = 'sale_out';
    case SaleVoid      = 'sale_void';
    case TransferOut   = 'transfer_out';
    case TransferIn    = 'transfer_in';
    case AdjustmentIn  = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
}