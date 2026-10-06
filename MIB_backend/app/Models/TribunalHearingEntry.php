<?php

namespace App\Models;

use App\Enums\TribunalHearingEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalHearingEntry extends Model
{
    protected $fillable = [
        'tribunal_hearing_id',
        'sender_id',
        'participant_type',
        'side',
        'entry_type',
        'body',
        'related_witness_id',
        'related_evidence_id',
        'sequence_number',
        'target_side',
        'parent_entry_id',
    ];

    protected $casts = [
        'entry_type' => TribunalHearingEntryType::class,
    ];

    public function hearing(): BelongsTo
    {
        return $this->belongsTo(TribunalHearing::class, 'tribunal_hearing_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function relatedWitness(): BelongsTo
    {
        return $this->belongsTo(TribunalWitness::class, 'related_witness_id');
    }

    public function relatedEvidence(): BelongsTo
    {
        return $this->belongsTo(TribunalEvidence::class, 'related_evidence_id');
    }

    public function parentEntry(): BelongsTo
    {
        return $this->belongsTo(TribunalHearingEntry::class, 'parent_entry_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TribunalHearingEntry::class, 'parent_entry_id')->orderBy('sequence_number', 'asc');
    }
}
