<?php

namespace App\Models;

use App\Enums\TribunalCaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TribunalCase extends Model
{
    protected $fillable = [
        'case_number',
        'created_by',
        'title',
        'category',
        'description',
        'requested_resolution',
        'status',
        'severity',
        'submitted_at',
    ];

    protected $casts = [
        'status' => TribunalCaseStatus::class,
        'submitted_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(TribunalCaseParty::class);
    }

    public function response(): HasOne
    {
        return $this->hasOne(TribunalCaseResponse::class, 'tribunal_case_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(TribunalEvidence::class, 'tribunal_case_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TribunalCaseEvent::class, 'tribunal_case_id')->orderBy('created_at', 'asc');
    }

    public function juryAssignments(): HasMany
    {
        return $this->hasMany(TribunalJuryAssignment::class, 'tribunal_case_id');
    }

    public function currentJuryAssignment(): HasOne
    {
        return $this->hasOne(TribunalJuryAssignment::class, 'tribunal_case_id')
            ->whereIn('status', [
                \App\Enums\TribunalJuryAssignmentStatus::Accepted,
                \App\Enums\TribunalJuryAssignmentStatus::Invited,
            ])
            ->latestOfMany();
    }

    public function acceptedJuryAssignment(): HasOne
    {
        return $this->hasOne(TribunalJuryAssignment::class, 'tribunal_case_id')
            ->where('status', \App\Enums\TribunalJuryAssignmentStatus::Accepted)
            ->latestOfMany();
    }

    public function juryPanelAssignments(): HasMany
    {
        return $this->hasMany(TribunalJuryPanelAssignment::class, 'tribunal_case_id');
    }

    public function currentJuryPanelAssignment(): HasOne
    {
        return $this->hasOne(TribunalJuryPanelAssignment::class, 'tribunal_case_id')
            ->where('status', \App\Enums\TribunalJuryPanelAssignmentStatus::Active);
    }

    public function isAssignedJuryPanelUser(int $userId): bool
    {
        if ($this->relationLoaded('currentJuryPanelAssignment')) {
            $assignment = $this->currentJuryPanelAssignment;
            if (!$assignment || !$assignment->isActive()) {
                return false;
            }
            $panel = $assignment->relationLoaded('juryPanel') 
                ? $assignment->juryPanel 
                : $assignment->juryPanel()->first();
            return $panel && $panel->isActive() && $panel->login_user_id === $userId;
        }

        return $this->currentJuryPanelAssignment()
            ->whereHas('juryPanel', function ($query) use ($userId) {
                $query->where('login_user_id', $userId)
                      ->where('status', \App\Enums\TribunalJuryPanelStatus::Active);
            })
            ->exists();
    }

    public function isComplainant(int $userId): bool
    {
        return $this->parties()
            ->where('user_id', $userId)
            ->where('role', \App\Enums\TribunalPartyRole::Complainant)
            ->exists();
    }

    public function isRespondent(int $userId): bool
    {
        return $this->parties()
            ->where('user_id', $userId)
            ->where('role', \App\Enums\TribunalPartyRole::Respondent)
            ->exists();
    }

    public function isParticipant(int $userId): bool
    {
        return $this->parties()
            ->where('user_id', $userId)
            ->exists();
    }

    public function isAcceptedJuror(int $userId): bool
    {
        return $this->juryAssignments()
            ->where('juror_id', $userId)
            ->where('status', \App\Enums\TribunalJuryAssignmentStatus::Accepted)
            ->exists();
    }

    public function isAcceptedAdjudicator(int $userId): bool
    {
        return $this->isAcceptedJuror($userId);
    }

    public function isAssignedJuror(int $userId): bool
    {
        return $this->juryAssignments()
            ->where('juror_id', $userId)
            ->whereIn('status', [
                \App\Enums\TribunalJuryAssignmentStatus::Accepted,
                \App\Enums\TribunalJuryAssignmentStatus::Invited,
            ])
            ->exists();
    }

    public function representationRequests(): HasMany
    {
        return $this->hasMany(TribunalRepresentationRequest::class, 'tribunal_case_id');
    }

    public function representativeAssignments(): HasMany
    {
        return $this->hasMany(TribunalRepresentativeAssignment::class, 'tribunal_case_id');
    }

    public function activeRepresentativeAssignments(): HasMany
    {
        return $this->hasMany(TribunalRepresentativeAssignment::class, 'tribunal_case_id')
            ->where('status', \App\Enums\TribunalRepresentativeAssignmentStatus::Active);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(TribunalConversation::class, 'tribunal_case_id');
    }

    public function isAcceptedRepresentative(int $userId): bool
    {
        return $this->representativeAssignments()
            ->where('representative_user_id', $userId)
            ->where('status', \App\Enums\TribunalRepresentativeAssignmentStatus::Active)
            ->exists();
    }

    public function activeRepresentativeFor(int $userId): ?TribunalRepresentativeAssignment
    {
        return $this->activeRepresentativeAssignments()
            ->where('client_user_id', $userId)
            ->with(['representative.profile', 'representative.latestProfessionalVerification'])
            ->first();
    }

    public function caseRoom(): HasOne
    {
        return $this->hasOne(TribunalCaseRoom::class, 'tribunal_case_id');
    }

    public function caseMessages(): HasMany
    {
        return $this->hasMany(TribunalCaseMessage::class, 'tribunal_case_id');
    }

    public function mediations(): HasMany
    {
        return $this->hasMany(TribunalMediation::class, 'tribunal_case_id');
    }

    public function activeMediation(): HasOne
    {
        return $this->hasOne(TribunalMediation::class, 'tribunal_case_id')
            ->whereIn('status', [
                \App\Enums\TribunalMediationStatus::Offered,
                \App\Enums\TribunalMediationStatus::AwaitingConsent,
                \App\Enums\TribunalMediationStatus::Active,
            ])
            ->latestOfMany();
    }

    public function settlementAgreements(): HasMany
    {
        return $this->hasMany(TribunalSettlementAgreement::class, 'tribunal_case_id');
    }

    public function settlementAgreement(): HasOne
    {
        return $this->hasOne(TribunalSettlementAgreement::class, 'tribunal_case_id')->latestOfMany();
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(TribunalHearing::class, 'tribunal_case_id')->orderBy('created_at', 'desc');
    }

    public function activeHearing(): HasOne
    {
        return $this->hasOne(TribunalHearing::class, 'tribunal_case_id')
            ->whereIn('status', [
                \App\Enums\TribunalHearingStatus::Scheduled,
                \App\Enums\TribunalHearingStatus::Active,
                \App\Enums\TribunalHearingStatus::Recessed,
            ])
            ->latestOfMany();
    }

    public function witnesses(): HasMany
    {
        return $this->hasMany(TribunalWitness::class, 'tribunal_case_id')->orderBy('created_at', 'desc');
    }

    public function deliberations(): HasMany
    {
        return $this->hasMany(TribunalDeliberation::class, 'tribunal_case_id');
    }

    public function deliberation(): HasOne
    {
        return $this->hasOne(TribunalDeliberation::class, 'tribunal_case_id')->latestOfMany();
    }

    public function findings(): HasMany
    {
        return $this->hasMany(TribunalFinding::class, 'tribunal_case_id')->orderBy('display_order', 'asc');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(TribunalDecision::class, 'tribunal_case_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TribunalCaseResponse::class, 'tribunal_case_id');
    }

    public function caseResponse(): HasOne
    {
        return $this->hasOne(TribunalCaseResponse::class, 'tribunal_case_id')->latestOfMany();
    }

    public function decision(): HasOne
    {
        return $this->hasOne(TribunalDecision::class, 'tribunal_case_id')->latestOfMany();
    }

    public function finalDecision(): HasOne
    {
        return $this->hasOne(TribunalDecision::class, 'tribunal_case_id')
            ->where('status', \App\Enums\TribunalDecisionStatus::Final)
            ->latestOfMany();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(TribunalCaseReport::class, 'tribunal_case_id')->orderBy('version', 'desc');
    }

    public function latestReport(): HasOne
    {
        return $this->hasOne(TribunalCaseReport::class, 'tribunal_case_id')->latestOfMany('version');
    }

    public function activeReport(): HasOne
    {
        return $this->hasOne(TribunalCaseReport::class, 'tribunal_case_id')
            ->where('status', 'generated')
            ->latestOfMany('version');
    }

    public function getUserCaseRole(int $userId): ?string
    {
        if ($this->isComplainant($userId)) {
            return 'complainant';
        }
        if ($this->isRespondent($userId)) {
            return 'respondent';
        }
        if ($this->isAssignedJuryPanelUser($userId)) {
            return 'jury_panel';
        }

        // For new Jury Panel-assigned cases, old individual adjudicators do NOT gain authority!
        $hasJuryPanel = $this->relationLoaded('currentJuryPanelAssignment')
            ? ($this->currentJuryPanelAssignment !== null)
            : $this->currentJuryPanelAssignment()->exists();

        if (!$hasJuryPanel && $this->isAcceptedAdjudicator($userId)) {
            return 'adjudicator';
        }

        $rep = $this->activeRepresentativeAssignments()
            ->where('representative_user_id', $userId)
            ->first();

        if ($rep) {
            if ($this->isComplainant($rep->client_user_id)) {
                return 'complainant_representative';
            }
            if ($this->isRespondent($rep->client_user_id)) {
                return 'respondent_representative';
            }
        }

        return null;
    }

    public function getUserCaseSide(int $userId): ?string
    {
        $role = $this->getUserCaseRole($userId);
        if ($role === 'complainant' || $role === 'complainant_representative') {
            return 'complainant';
        }
        if ($role === 'respondent' || $role === 'respondent_representative') {
            return 'respondent';
        }
        return null;
    }

    public function isAuthorizedToView(int $userId): bool
    {
        return $this->isParticipant($userId) 
            || $this->isAcceptedJuror($userId) 
            || $this->isAcceptedRepresentative($userId)
            || $this->isAssignedJuryPanelUser($userId);
    }
}
