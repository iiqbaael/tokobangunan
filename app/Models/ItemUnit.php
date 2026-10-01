<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUnit extends Model
{
    use \App\Traits\AuditableTrait;

    protected $fillable = [
        'item_id',
        'unit',
        'conversion_qty',
        'barcode',
        'sell_price',
    ];

    protected function casts(): array
    {
        return [
            'conversion_qty' => 'decimal:4',
            'sell_price' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}