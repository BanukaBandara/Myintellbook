<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalCaseReportDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribunal_case_report_id',
        'downloaded_by_user_id',
        'downloaded_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(TribunalCaseReport::class, 'tribunal_case_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'downloaded_by_user_id');
    }
}
