<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalSettlementAcceptance extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_proposal_id',
        'user_id',
        'side',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(TribunalSettlementProposal::class, 'settlement_proposal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
