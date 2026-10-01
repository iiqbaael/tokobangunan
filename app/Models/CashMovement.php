<?php

namespace App\Models;

use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use \App\Traits\AuditableTrait;

    // Tabel ini hanya punya created_at (ERD §4.4)
    const UPDATED_AT = null;

    protected $fillable = [
        'cashier_shift_id',
        'type',
        'amount',
        'reason',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'decimal:2',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}