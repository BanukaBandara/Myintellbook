<?php

namespace App\Models;

use App\Enums\InternalPenaltyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalPenalty extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_report_id',
        'user_id',
        'action_type',
        'reason',
        'notes',
        'applied_by',
        'applied_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected $casts = [
        'action_type' => InternalPenaltyType::class,
        'applied_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(InternalReport::class, 'internal_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
