<?php

namespace App\Models;

use App\Enums\TribunalEvidenceChallengeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalEvidenceChallenge extends Model
{
    protected $fillable = [
        'tribunal_evidence_id',
        'challenged_by',
        'reason',
        'status',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => TribunalEvidenceChallengeStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(TribunalEvidence::class, 'tribunal_evidence_id');
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenged_by');
    }
}
