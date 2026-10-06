<?php

namespace App\Models;

use App\Enums\TribunalJuryPanelAssignmentMethod;
use App\Enums\TribunalJuryPanelAssignmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalJuryPanelAssignment extends Model
{
    use HasFactory;

    protected $table = 'tribunal_jury_panel_assignments';

    protected $fillable = [
        'tribunal_case_id',
        'tribunal_jury_panel_id',
        'assigned_by',
        'assignment_method',
        'status',
        'assigned_at',
        'released_at',
        'release_reason',
    ];

    protected $casts = [
        'status' => TribunalJuryPanelAssignmentStatus::class,
        'assignment_method' => TribunalJuryPanelAssignmentMethod::class,
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function tribunalCase(): BelongsTo
    {
        return $this->belongsTo(TribunalCase::class, 'tribunal_case_id');
    }

    public function juryPanel(): BelongsTo
    {
        return $this->belongsTo(TribunalJuryPanel::class, 'tribunal_jury_panel_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActive(): bool
    {
        return $this->status === TribunalJuryPanelAssignmentStatus::Active;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TribunalJuryPanelAssignmentStatus::Active);
    }
}
