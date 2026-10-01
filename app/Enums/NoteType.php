<?php

namespace App\Enums;

enum NoteType: string
{
    case General    = 'general';
    case Pembayaran = 'pembayaran';
    case Titipan    = 'titipan';
}