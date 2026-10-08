<?php

namespace App\Models;

use App\Enums\TribunalDecisionOrderStatus;
use App\Enums\TribunalDecisionOrderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalDecisionOrder extends Model
{
    protected $fillable = [
        'tribunal_decision_id',
        'order_number',
        'order_type',
        'title',
        'description',
        'target_side',
        'deadline_at',
        'status',
    ];

    protected $casts = [
        'order_type' => TribunalDecisionOrderType::class,
        'status' => TribunalDecisionOrderStatus::class,
        'deadline_at' => 'datetime',
    ];

    public function decision(): BelongsTo
    {
        return $this->belongsTo(TribunalDecision::class, 'tribunal_decision_id');
    }
}
