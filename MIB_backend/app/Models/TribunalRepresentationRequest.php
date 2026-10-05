<?php

namespace App\Models;

use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TribunalRepresentationRequest extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'requested_by',
        'client_user_id',
        'representative_user_id',
        'side',
        'status',
        'message',
        'requested_at',
        'responded_at',
        'decline_reason',
    ];

    protected $casts = [
        'status' => TribunalRepresentationRequestStatus::class,
        'side' => TribunalPartyRole::class,
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_user_id');
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(TribunalRepresentativeAssignment::class, 'representation_request_id');
    }

    public function isPending(): bool
    {
        return $this->status === TribunalRepresentationRequestStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === TribunalRepresentationRequestStatus::Accepted;
    }

    public function isDeclined(): bool
    {
        return $this->status === TribunalRepresentationRequestStatus::Declined;
    }
}
