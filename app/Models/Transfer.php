<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    use \App\Traits\AuditableTrait;

    protected $fillable = [
        'from_branch_id',
        'to_branch_id',
        'transfer_no',
        'transfer_date',
        'status',
        'note',
        'created_by',
        'sent_by',
        'received_by',
        'sent_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'status' => TransferStatus::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransferDetail::class);
    }
}