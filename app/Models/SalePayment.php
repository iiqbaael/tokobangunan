<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    // Tabel ini hanya punya created_at (ERD §4.3)
    const UPDATED_AT = null;

    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'reference_no',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(SalesHeader::class, 'sale_id');
    }
}