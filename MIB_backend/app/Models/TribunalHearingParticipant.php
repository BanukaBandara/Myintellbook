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

    /**
     * Resolve safe display name with profile name or safe role fallback.
     */
    public function getDisplayNameAttribute($value): string
    {
        if ($this->participant_type === 'jury_panel') {
            return $value ?: 'Jury Panel';
        }

        $profile = $this->user?->profile;
        if ($profile && filled($profile->first_name)) {
            $fullName = trim("{$profile->first_name} {$profile->last_name}");
            if (str_contains($this->participant_type, 'representative')) {
                return "{$fullName} (Counsel)";
            }
            return $fullName;
        }

        if ($value && !preg_match('/^Party\s+\d+$/i', $value)) {
            return $value;
        }

        return match ($this->participant_type) {
            'complainant' => 'Complainant',
            'respondent' => 'Respondent',
            'complainant_representative', 'respondent_representative' => 'Counsel',
            default => 'Participant',
        };
    }
}
