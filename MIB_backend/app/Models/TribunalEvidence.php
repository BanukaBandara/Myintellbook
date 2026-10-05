<?php

namespace App\Models;

use App\Enums\TribunalEvidenceStatus;
use App\Enums\TribunalEvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalEvidence extends Model
{
    protected $table = 'tribunal_evidence';

    protected $fillable = [
        'tribunal_case_id',
        'uploaded_by',
        'evidence_number',
        'type',
        'title',
        'description',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        'sha256_hash',
        'external_url',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'type' => TribunalEvidenceType::class,
        'status' => TribunalEvidenceStatus::class,
        'file_size' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(TribunalEvidenceChallenge::class, 'tribunal_evidence_id');
    }
}
