<?php

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use \App\Traits\AuditableTrait;

    protected $fillable = [
        'branch_id',
        'adjustment_no',
        'adjustment_date',
        'adjustment_type',
        'reason',
        'reference_no',
        'description',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'adjustment_type' => AdjustmentType::class,
            'reason' => AdjustmentReason::class,
            'status' => AdjustmentStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockAdjustmentDetail::class);
    }
}