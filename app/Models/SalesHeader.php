<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesHeader extends Model
{
    use \App\Traits\AuditableTrait;

    protected $table = 'sales_headers';

    protected $fillable = [
        'branch_id',
        'cashier_shift_id',
        'customer_id',
        'invoice_no',
        'sale_date',
        'total',
        'cash_received',
        'change_given',
        'status',
        'voided_by',
        'voided_at',
        'void_reason',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'datetime',
            'total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_given' => 'decimal:2',
            'status' => SaleStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SalesDetail::class, 'sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_id');
    }
}