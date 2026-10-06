<?php

namespace App\Models;

use App\Enums\TribunalHearingLocationType;
use App\Enums\TribunalHearingStatus;
use App\Enums\TribunalHearingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalHearing extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'tribunal_jury_panel_id',
        'hearing_number',
        'hearing_type',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'location_type',
        'meeting_link',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'hearing_type' => TribunalHearingType::class,
        'status' => TribunalHearingStatus::class,
        'location_type' => TribunalHearingLocationType::class,
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function juryPanel(): BelongsTo
    {
        return $this->belongsTo(TribunalJuryPanel::class, 'tribunal_jury_panel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TribunalHearingParticipant::class, 'tribunal_hearing_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TribunalHearingEntry::class, 'tribunal_hearing_id')->orderBy('sequence_number', 'asc');
    }

    public function witnesses(): HasMany
    {
        return $this->hasMany(TribunalWitness::class, 'tribunal_hearing_id');
    }

    public function isActive(): bool
    {
        return $this->status === TribunalHearingStatus::Active;
    }

    public function isScheduled(): bool
    {
        return $this->status === TribunalHearingStatus::Scheduled;
    }

    public function isRecessed(): bool
    {
        return $this->status === TribunalHearingStatus::Recessed;
    }

    public function isCompleted(): bool
    {
        return $this->status === TribunalHearingStatus::Completed;
    }
}
