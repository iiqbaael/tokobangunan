<?php

namespace App\Models;

use App\Enums\NoteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Note extends Model
{
    use \App\Traits\AuditableTrait;

    protected $fillable = [
        'noteable_type',
        'noteable_id',
        'type',
        'body',
        'amount',
        'follow_up_date',
        'is_done',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => NoteType::class,
            'amount' => 'decimal:2',
            'follow_up_date' => 'date',
            'is_done' => 'boolean',
        ];
    }

    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}