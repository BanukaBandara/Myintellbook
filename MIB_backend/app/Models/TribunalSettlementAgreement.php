<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalSettlementAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribunal_case_id',
        'tribunal_mediation_id',
        'settlement_proposal_id',
        'agreement_number',
        'terms_snapshot',
        'complainant_accepted_at',
        'respondent_accepted_at',
        'finalized_at',
    ];

    protected $casts = [
        'complainant_accepted_at' => 'datetime',
        'respondent_accepted_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function mediation(): BelongsTo
    {
        return $this->belongsTo(TribunalMediation::class, 'tribunal_mediation_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(TribunalSettlementProposal::class, 'settlement_proposal_id');
    }
}
