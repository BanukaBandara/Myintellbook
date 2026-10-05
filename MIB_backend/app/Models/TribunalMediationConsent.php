<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalMediationConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribunal_mediation_id',
        'user_id',
        'side',
        'response',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function mediation(): BelongsTo
    {
        return $this->belongsTo(TribunalMediation::class, 'tribunal_mediation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isAccepted(): bool
    {
        return $this->response === 'accepted';
    }

    public function isDeclined(): bool
    {
        return $this->response === 'declined';
    }

    public function isPending(): bool
    {
        return $this->response === 'pending';
    }
}
