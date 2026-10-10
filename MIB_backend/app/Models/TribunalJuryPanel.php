<?php

namespace App\Models;

use App\Enums\TribunalJuryPanelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TribunalJuryPanel extends Model
{
    use HasFactory;

    protected $fillable = [
        'panel_code',
        'panel_name',
        'login_user_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => TribunalJuryPanelStatus::class,
    ];

    public function loginUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'login_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TribunalJuryPanelEvent::class, 'tribunal_jury_panel_id');
    }

    public function panelAssignments(): HasMany
    {
        return $this->hasMany(TribunalJuryPanelAssignment::class, 'tribunal_jury_panel_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TribunalJuryPanelAssignment::class, 'tribunal_jury_panel_id')
            ->where('status', \App\Enums\TribunalJuryPanelAssignmentStatus::Active);
    }

    public function assignedCases(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            TribunalCase::class,
            'tribunal_jury_panel_assignments',
            'tribunal_jury_panel_id',
            'tribunal_case_id'
        )->withPivot(['id', 'assignment_method', 'status', 'assigned_at', 'released_at'])
         ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === TribunalJuryPanelStatus::Active;
    }

    /**
     * Generate a unique sequential human-readable panel code (e.g. JP-0001).
     */
    public static function generateUniqueCode(): string
    {
        $panels = DB::table('tribunal_jury_panels')
            ->select('panel_code')
            ->get();

        $max = 0;
        foreach ($panels as $p) {
            if (preg_match('/^JP-(\d+)$/', (string) $p->panel_code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        $next = $max + 1;
        $code = sprintf('JP-%04d', $next);

        while (DB::table('tribunal_jury_panels')->where('panel_code', $code)->exists()) {
            $next++;
            $code = sprintf('JP-%04d', $next);
        }

        return $code;
    }
}
