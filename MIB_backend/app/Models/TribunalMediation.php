<?php

namespace App\Models;

use App\Enums\TribunalMediationInitiationType;
use App\Enums\TribunalMediationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TribunalMediation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribunal_case_id',
        'initiated_by',
        'initiation_type',
        'status',
        'previous_case_status',
        'offered_at',
        'started_at',
        'ended_at',
        'failure_reason',
    ];

    protected $casts = [
        'status' => TribunalMediationStatus::class,
        'initiation_type' => TribunalMediationInitiationType::class,
        'offered_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(TribunalMediationConsent::class, 'tribunal_mediation_id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(TribunalSettlementProposal::class, 'tribunal_mediation_id');
    }

    public function settlementAgreement(): HasOne
    {
        return $this->hasOne(TribunalSettlementAgreement::class, 'tribunal_mediation_id');
    }

    public function isActive(): bool
    {
        return $this->status === TribunalMediationStatus::Active;
    }

    public function isSettled(): bool
    {
        return $this->status === TribunalMediationStatus::Settled;
    }
}
