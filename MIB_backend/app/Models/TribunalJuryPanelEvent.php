<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalJuryPanelEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tribunal_jury_panel_id',
        'actor_id',
        'event_type',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function panel(): BelongsTo
    {
        return $this->belongsTo(TribunalJuryPanel::class, 'tribunal_jury_panel_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
