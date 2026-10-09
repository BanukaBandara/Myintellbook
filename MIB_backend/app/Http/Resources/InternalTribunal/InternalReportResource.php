<?php

namespace App\Http\Resources\InternalTribunal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternalReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reportedUser = $this->reportedUser;
        $profile = $reportedUser?->profile;

        $reportedUserName = $profile && ($profile->first_name || $profile->last_name)
            ? trim("{$profile->first_name} {$profile->last_name}")
            : ($reportedUser ? "User #{$reportedUser->id}" : 'Deleted User');

        $slug = $profile?->slug;
        $reportedUserUsername = $slug ? ltrim($slug, '@') : ($reportedUser ? "user{$reportedUser->id}" : 'unknown');

        $profilePhotoUrl = null;
        if ($profile && !empty($profile->profile_image)) {
            $rawImage = $profile->profile_image;
            if (str_starts_with($rawImage, 'data:image') || str_starts_with($rawImage, 'http://') || str_starts_with($rawImage, 'https://')) {
                $profilePhotoUrl = $rawImage;
            } elseif (str_starts_with($rawImage, '/')) {
                $profilePhotoUrl = url($rawImage);
            } else {
                $profilePhotoUrl = asset('storage/' . $rawImage);
            }
        }

        return [
            'id' => $this->id,
            'report_number' => $this->report_number,
            'reported_user' => [
                'id' => $reportedUser?->id,
                'name' => $reportedUserName,
                'username' => $reportedUserUsername,
                'profile_image' => $profilePhotoUrl,
            ],
            'category' => $this->category?->value ?? $this->category,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'severity' => $this->severity,
            'decision_reason' => in_array($this->status?->value ?? $this->status, ['Valid', 'Invalid', 'Closed'])
                ? $this->decision_reason
                : null,
            'evidence' => InternalReportEvidenceResource::collection($this->whenLoaded('evidence')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
