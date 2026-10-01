<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    // Tabel ini hanya punya updated_at (ERD §4.5)
    const CREATED_AT = null;

    protected $fillable = [
        'branch_id',
        'item_id',
        'qty',
        'avg_cost',
        'min_stock',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'avg_cost' => 'decimal:4',
            'min_stock' => 'decimal:4',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}