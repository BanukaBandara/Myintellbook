<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalReportEvidence extends Model
{
    use HasFactory;

    protected $table = 'internal_report_evidence';

    protected $fillable = [
        'internal_report_id',
        'uploaded_by',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'sha256',
        'is_confidential',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_confidential' => 'boolean',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(InternalReport::class, 'internal_report_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
