<?php

namespace App\Models;

use App\Enums\TribunalJurorEligibilityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalJurorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'qualified_at',
        'training_completed_at',
        'available',
        'cases_active',
        'notes',
    ];

    protected $casts = [
        'status' => TribunalJurorEligibilityStatus::class,
        'available' => 'boolean',
        'cases_active' => 'integer',
        'qualified_at' => 'datetime',
        'training_completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
