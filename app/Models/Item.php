<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use \App\Traits\AuditableTrait;

    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'code',
        'barcode',
        'name',
        'brand',
        'specification',
        'unit',
        'sell_price',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sell_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ItemUnit::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }
}