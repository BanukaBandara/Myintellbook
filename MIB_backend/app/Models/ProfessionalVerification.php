<?php

namespace App\Models;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProfessionalVerification extends Model
{
    protected $fillable = [
        'user_id',
        'profession_type',
        'verification_status',
        'registration_number',
        'enrollment_number',
        'issuing_authority',
        'years_of_experience',
        'qualification_document_path',
        'identity_document_path',
        'additional_document_path',
        'submitted_at',
        'reviewed_at',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'suspension_reason',
        'expires_at',
    ];

    protected $casts = [
        'profession_type' => ProfessionalType::class,
        'verification_status' => ProfessionalVerificationStatus::class,
        'years_of_experience' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProfessionalVerificationEvent::class, 'professional_verification_id')
            ->orderBy('created_at', 'asc');
    }

    public function adjudicatorProfile(): HasOne
    {
        return $this->hasOne(TribunalAdjudicatorProfile::class, 'professional_verification_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === ProfessionalVerificationStatus::Verified;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSuspended(): bool
    {
        return $this->verification_status === ProfessionalVerificationStatus::Suspended;
    }

    public function isValid(): bool
    {
        return $this->isVerified() && !$this->isExpired() && !$this->isSuspended();
    }

    public function maskedRegistrationNumber(): ?string
    {
        if (empty($this->registration_number)) {
            return null;
        }

        return '****' . substr($this->registration_number, -4);
    }

    public function maskedEnrollmentNumber(): ?string
    {
        if (empty($this->enrollment_number)) {
            return null;
        }

        return '****' . substr($this->enrollment_number, -4);
    }
}
