<?php

namespace App\Models;

use App\Enums\TribunalSettlementProposalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalSettlementProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribunal_mediation_id',
        'proposed_by',
        'proposed_by_side',
        'parent_proposal_id',
        'version_number',
        'terms',
        'status',
    ];

    protected $casts = [
        'status' => TribunalSettlementProposalStatus::class,
        'version_number' => 'integer',
    ];

    public function mediation(): BelongsTo
    {
        return $this->belongsTo(TribunalMediation::class, 'tribunal_mediation_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function parentProposal(): BelongsTo
    {
        return $this->belongsTo(TribunalSettlementProposal::class, 'parent_proposal_id');
    }

    public function counterProposals(): HasMany
    {
        return $this->hasMany(TribunalSettlementProposal::class, 'parent_proposal_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(TribunalSettlementAcceptance::class, 'settlement_proposal_id');
    }

    public function isAcceptedBy(int $userId): bool
    {
        return $this->acceptances()->where('user_id', $userId)->exists();
    }
}
