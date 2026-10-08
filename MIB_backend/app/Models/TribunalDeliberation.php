<?php

namespace App\Models;

use App\Enums\TribunalDeliberationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TribunalDeliberation extends Model
{
    protected $fillable = [
        'tribunal_case_id',
        'tribunal_jury_panel_id',
        'status',
        'opened_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => TribunalDeliberationStatus::class,
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function juryPanel(): BelongsTo
    {
        return $this->belongsTo(TribunalJuryPanel::class, 'tribunal_jury_panel_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TribunalDeliberationNote::class, 'tribunal_deliberation_id')->orderBy('created_at', 'desc');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(TribunalFinding::class, 'tribunal_deliberation_id')->orderBy('display_order', 'asc');
    }

    public function isOpen(): bool
    {
        return $this->status === TribunalDeliberationStatus::Open;
    }

    public function isCompleted(): bool
    {
        return $this->status === TribunalDeliberationStatus::Completed;
    }
}
