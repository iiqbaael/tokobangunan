<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    use \App\Traits\AuditableTrait;

    // open_branch_id (generated column) sengaja tidak masuk fillable
    protected $fillable = [
        'branch_id',
        'user_id',
        'closed_by',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'difference',
        'status',
        'opened_at',
        'closed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'expected_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'status' => ShiftStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function salesHeaders(): HasMany
    {
        return $this->hasMany(SalesHeader::class, 'cashier_shift_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cashier_shift_id');
    }
}