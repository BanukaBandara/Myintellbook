<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalHearingParticipant extends Model
{
    protected $fillable = [
        'tribunal_hearing_id',
        'user_id',
        'participant_type',
        'side',
        'display_name',
        'invited_by',
        'attendance_status',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function hearing(): BelongsTo
    {
        return $this->belongsTo(TribunalHearing::class, 'tribunal_hearing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
