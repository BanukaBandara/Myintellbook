<?php

namespace App\Models;

use App\Enums\TribunalResponsePosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalCaseResponse extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'respondent_id',
        'acknowledgement_at',
        'position',
        'response_text',
        'submitted_at',
    ];

    protected $casts = [
        'position' => TribunalResponsePosition::class,
        'acknowledgement_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }
}
