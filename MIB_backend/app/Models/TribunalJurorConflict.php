<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalJurorConflict extends Model
{
    protected $fillable = [
        'tribunal_jury_assignment_id',
        'juror_id',
        'has_conflict',
        'conflict_reason',
        'declared_at',
    ];

    protected $casts = [
        'has_conflict' => 'boolean',
        'declared_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TribunalJuryAssignment::class, 'tribunal_jury_assignment_id');
    }

    public function juror(): BelongsTo
    {
        return $this->belongsTo(User::class, 'juror_id');
    }
}
