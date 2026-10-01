<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentDetail extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'qty',
        'unit_cost',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}