<?php

namespace App\Models;

use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_number',
        'reporter_user_id',
        'reported_user_id',
        'category',
        'subject',
        'description',
        'status',
        'severity',
        'admin_notes',
        'decision_reason',
        'reviewed_by',
        'reviewed_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => InternalReportStatus::class,
        'category' => InternalReportCategory::class,
        'reviewed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(InternalReportEvidence::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(InternalReportReview::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(InternalPenalty::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(InternalReportAudit::class);
    }
}
