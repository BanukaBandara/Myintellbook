<?php

namespace App\Http\Resources\InternalTribunal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminInternalReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reporter = $this->reporter;
        $reportedUser = $this->reportedUser;
        $reporterProfile = $reporter?->profile;
        $reportedProfile = $reportedUser?->profile;

        $reporterName = $reporterProfile && ($reporterProfile->first_name || $reporterProfile->last_name)
            ? trim("{$reporterProfile->first_name} {$reporterProfile->last_name}")
            : ($reporter ? "User #{$reporter->id}" : 'Deleted User');

        $reportedUserName = $reportedProfile && ($reportedProfile->first_name || $reportedProfile->last_name)
            ? trim("{$reportedProfile->first_name} {$reportedProfile->last_name}")
            : ($reportedUser ? "User #{$reportedUser->id}" : 'Deleted User');

        return [
            'id' => $this->id,
            'report_number' => $this->report_number,
            'reporter' => [
                'id' => $reporter?->id,
                'name' => $reporterName,
                'email' => $reporter?->email,
                'username' => $reporterProfile?->slug ? ltrim($reporterProfile->slug, '@') : ($reporter ? "user{$reporter->id}" : null),
            ],
            'reported_user' => [
                'id' => $reportedUser?->id,
                'name' => $reportedUserName,
                'email' => $reportedUser?->email,
                'username' => $reportedProfile?->slug ? ltrim($reportedProfile->slug, '@') : ($reportedUser ? "user{$reportedUser->id}" : null),
                'is_jury_panel' => $reportedUser?->juryPanel !== null,
            ],
            'category' => $this->category?->value ?? $this->category,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'severity' => $this->severity,
            'admin_notes' => $this->admin_notes,
            'decision_reason' => $this->decision_reason,
            'reviewed_by' => $this->reviewed_by,
            'reviewer_name' => $this->reviewer?->profile
                ? trim("{$this->reviewer->profile->first_name} {$this->reviewer->profile->last_name}")
                : ($this->reviewer ? "Admin #{$this->reviewer->id}" : null),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'evidence' => InternalReportEvidenceResource::collection($this->whenLoaded('evidence')),
            'reviews' => $this->reviews?->map(function ($review) {
                return [
                    'id' => $review->id,
                    'from_status' => $review->from_status?->value ?? $review->from_status,
                    'to_status' => $review->to_status?->value ?? $review->to_status,
                    'notes' => $review->notes,
                    'reviewer_name' => $review->reviewer?->profile
                        ? trim("{$review->reviewer->profile->first_name} {$review->reviewer->profile->last_name}")
                        : ($review->reviewer ? "Admin #{$review->reviewer->id}" : 'System'),
                    'created_at' => $review->created_at?->toIso8601String(),
                ];
            }),
            'penalties' => $this->penalties?->map(function ($penalty) {
                return [
                    'id' => $penalty->id,
                    'action_type' => $penalty->action_type?->value ?? $penalty->action_type,
                    'reason' => $penalty->reason,
                    'notes' => $penalty->notes,
                    'applied_by' => $penalty->applied_by,
                    'applied_by_name' => $penalty->applier?->profile
                        ? trim("{$penalty->applier->profile->first_name} {$penalty->applier->profile->last_name}")
                        : ($penalty->applier ? "Admin #{$penalty->applier->id}" : null),
                    'applied_at' => $penalty->applied_at?->toIso8601String(),
                ];
            }),
            'audits' => $this->audits?->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'action' => $audit->action,
                    'performed_by' => $audit->performed_by,
                    'performer_name' => $audit->performer?->profile
                        ? trim("{$audit->performer->profile->first_name} {$audit->performer->profile->last_name}")
                        : ($audit->performer ? "Admin #{$audit->performer->id}" : 'System'),
                    'details' => $audit->details,
                    'created_at' => $audit->created_at?->toIso8601String(),
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
