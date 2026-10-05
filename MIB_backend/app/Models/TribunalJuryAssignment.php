<?php

namespace App\Models;

use App\Enums\TribunalJuryAssignmentStatus;
use App\Enums\TribunalJuryRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TribunalJuryAssignment extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'juror_id',
        'role',
        'status',
        'assigned_at',
        'responded_at',
        'recusal_reason',
    ];

    protected $casts = [
        'role' => TribunalJuryRole::class,
        'status' => TribunalJuryAssignmentStatus::class,
        'assigned_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function juror(): BelongsTo
    {
        return $this->belongsTo(User::class, 'juror_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(TribunalJurorConflict::class, 'tribunal_jury_assignment_id');
    }

    public function conflict(): HasOne
    {
        return $this->hasOne(TribunalJurorConflict::class, 'tribunal_jury_assignment_id')->latestOfMany();
    }
}
