<?php

namespace App\Models;

use App\Enums\DifferenceResolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferDetail extends Model
{
    protected $fillable = [
        'transfer_id',
        'item_id',
        'qty_sent',
        'qty_received',
        'unit_cost',
        'difference_resolution',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'qty_sent' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'difference_resolution' => DifferenceResolution::class,
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}