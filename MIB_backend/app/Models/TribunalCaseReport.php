<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalCaseReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribunal_case_id',
        'tribunal_decision_id',
        'report_number',
        'verification_code',
        'report_type',
        'status',
        'version',
        'file_path',
        'file_hash',
        'issued_at',
        'generated_by_user_id',
        'generated_at',
        'last_downloaded_at',
        'download_count',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'generated_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'version' => 'integer',
        'download_count' => 'integer',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(TribunalDecision::class, 'tribunal_decision_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(TribunalCaseReportDownload::class, 'tribunal_case_report_id');
    }

    public function isGenerated(): bool
    {
        return $this->status === 'generated';
    }

    public function isSuperseded(): bool
    {
        return $this->status === 'superseded';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }
}
