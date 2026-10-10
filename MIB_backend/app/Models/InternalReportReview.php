<?php

namespace App\Models;

use App\Enums\InternalReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalReportReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_report_id',
        'reviewed_by',
        'from_status',
        'to_status',
        'notes',
    ];

    protected $casts = [
        'from_status' => InternalReportStatus::class,
        'to_status' => InternalReportStatus::class,
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(InternalReport::class, 'internal_report_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
