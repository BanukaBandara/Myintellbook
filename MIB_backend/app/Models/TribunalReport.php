<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalReport extends Model
{
    protected $fillable = [
        'user_id',
        'tribunal_case_id',
        'violation_type',
        'details',
        'status',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
