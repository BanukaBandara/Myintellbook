<?php

namespace App\Models;

use App\Enums\TribunalAdjudicatorStatus;
use App\Enums\TribunalQualificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TribunalAdjudicatorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'professional_verification_id',
        'status',
        'qualification_status',
        'qualification_exam_id',
        'qualification_score',
        'qualified_at',
        'training_completed_at',
        'available',
        'max_active_cases',
        'cases_active',
        'experience_level',
        'suspended_at',
        'suspension_reason',
        'notes',
    ];

    protected $casts = [
        'status' => TribunalAdjudicatorStatus::class,
        'qualification_status' => TribunalQualificationStatus::class,
        'qualification_score' => 'float',
        'available' => 'boolean',
        'max_active_cases' => 'integer',
        'cases_active' => 'integer',
        'qualified_at' => 'datetime',
        'training_completed_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function professionalVerification(): BelongsTo
    {
        return $this->belongsTo(ProfessionalVerification::class, 'professional_verification_id');
    }

    public function qualificationExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'qualification_exam_id');
    }

    /**
     * Complete check if this profile qualifies to sit on a Tribunal panel.
     */
    public function isEligibleAdjudicator(): bool
    {
        if ($this->status !== TribunalAdjudicatorStatus::Eligible) {
            return false;
        }

        if (!$this->available) {
            return false;
        }

        if (!$this->qualification_status instanceof TribunalQualificationStatus || !$this->qualification_status->isSatisfied()) {
            return false;
        }

        $verification = $this->professionalVerification;
        if (!$verification || !$verification->isValid()) {
            return false;
        }

        return $verification->profession_type->isAdjudicatorEligible();
    }
}
