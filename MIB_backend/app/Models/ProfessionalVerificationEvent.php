<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalVerificationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'professional_verification_id',
        'actor_id',
        'event_type',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(ProfessionalVerification::class, 'professional_verification_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
