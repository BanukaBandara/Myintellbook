<?php

namespace App\Models;

use App\Enums\TribunalFindingConclusion;
use App\Enums\TribunalFindingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TribunalFinding extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'tribunal_deliberation_id',
        'finding_number',
        'finding_type',
        'title',
        'finding_text',
        'conclusion',
        'display_order',
        'is_public',
        'created_by',
    ];

    protected $casts = [
        'finding_type' => TribunalFindingType::class,
        'conclusion' => TribunalFindingConclusion::class,
        'display_order' => 'integer',
        'is_public' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function deliberation(): BelongsTo
    {
        return $this->belongsTo(TribunalDeliberation::class, 'tribunal_deliberation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            TribunalEvidence::class,
            'tribunal_finding_evidence',
            'tribunal_finding_id',
            'tribunal_evidence_id'
        );
    }

    public function hearingEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            TribunalHearingEntry::class,
            'tribunal_finding_hearing_entries',
            'tribunal_finding_id',
            'tribunal_hearing_entry_id'
        );
    }

    public function witnesses(): BelongsToMany
    {
        return $this->belongsToMany(
            TribunalWitness::class,
            'tribunal_finding_witnesses',
            'tribunal_finding_id',
            'tribunal_witness_id'
        );
    }
}
