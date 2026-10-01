<?php

namespace App\Models;

use App\Enums\StockMutationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMutation extends Model
{
    // Tabel ini hanya punya created_at (ERD §4.5)
    const UPDATED_AT = null;

    protected $fillable = [
        'branch_id',
        'item_id',
        'mutation_date',
        'mutation_type',
        'reference_type',
        'reference_id',
        'qty',
        'unit_cost',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'mutation_date' => 'datetime',
            'mutation_type' => StockMutationType::class,
            'qty' => 'decimal:4',
            'unit_cost' => 'decimal:4',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}