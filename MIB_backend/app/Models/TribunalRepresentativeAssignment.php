<?php

namespace App\Models;

use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalRepresentativeAssignment extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'client_user_id',
        'representative_user_id',
        'side',
        'representation_request_id',
        'status',
        'accepted_at',
        'ended_at',
        'ended_by',
        'end_reason',
    ];

    protected $casts = [
        'status' => TribunalRepresentativeAssignmentStatus::class,
        'side' => TribunalPartyRole::class,
        'accepted_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_user_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TribunalRepresentationRequest::class, 'representation_request_id');
    }

    public function endedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function isActive(): bool
    {
        return $this->status === TribunalRepresentativeAssignmentStatus::Active;
    }
}
