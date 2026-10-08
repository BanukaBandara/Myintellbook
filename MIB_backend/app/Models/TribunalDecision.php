<?php

namespace App\Models;

use App\Enums\TribunalDecisionOutcome;
use App\Enums\TribunalDecisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalDecision extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'tribunal_jury_panel_id',
        'decision_number',
        'status',
        'outcome',
        'summary',
        'reasoning',
        'published_at',
        'appeal_deadline',
        'created_by',
    ];

    protected $casts = [
        'status' => TribunalDecisionStatus::class,
        'outcome' => TribunalDecisionOutcome::class,
        'published_at' => 'datetime',
        'appeal_deadline' => 'datetime',
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

    public function orders(): HasMany
    {
        return $this->hasMany(TribunalDecisionOrder::class, 'tribunal_decision_id');
    }

    public function isFinal(): bool
    {
        return $this->status === TribunalDecisionStatus::Final;
    }

    public function isDraft(): bool
    {
        return $this->status === TribunalDecisionStatus::Draft;
    }
}
