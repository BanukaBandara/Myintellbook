<?php

namespace App\Models;

use App\Enums\TribunalWitnessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalWitness extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'tribunal_hearing_id',
        'proposed_by',
        'side',
        'witness_user_id',
        'witness_name',
        'witness_email',
        'relationship_to_case',
        'statement_summary',
        'status',
        'approved_by_panel_at',
        'rejected_reason',
    ];

    protected $casts = [
        'status' => TribunalWitnessStatus::class,
        'approved_by_panel_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function hearing(): BelongsTo
    {
        return $this->belongsTo(TribunalHearing::class, 'tribunal_hearing_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function witnessUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TribunalHearingEntry::class, 'related_witness_id');
    }

    public function isApproved(): bool
    {
        return $this->status === TribunalWitnessStatus::Approved;
    }

    public function isTestified(): bool
    {
        return $this->status === TribunalWitnessStatus::Testified;
    }

    public function isProposed(): bool
    {
        return $this->status === TribunalWitnessStatus::Proposed;
    }
}
