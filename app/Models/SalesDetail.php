<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDetail extends Model
{
    protected $table = 'sales_details';

    protected $fillable = [
        'sale_id',
        'item_id',
        'unit',
        'qty_input',
        'conversion_qty',
        'qty',
        'unit_price',
        'subtotal',
        'cost_at_sale',
    ];

    protected function casts(): array
    {
        return [
            'qty_input' => 'decimal:4',
            'conversion_qty' => 'decimal:4',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'cost_at_sale' => 'decimal:4',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(SalesHeader::class, 'sale_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}