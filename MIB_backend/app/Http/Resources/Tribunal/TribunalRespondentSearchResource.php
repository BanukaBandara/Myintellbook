<?php

namespace App\Http\Resources\Tribunal;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalRespondentSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $profile = $user->profile;

        // Clean display name
        $name = $profile && ($profile->first_name || $profile->last_name)
            ? trim("{$profile->first_name} {$profile->last_name}")
            : "User #{$user->id}";

        // Safe public username / identifier
        $slug = $profile?->slug;
        $username = $slug ? ltrim($slug, '@') : "user{$user->id}";

        // Format browser-safe profile image
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

        // Determine safe public subtitle
        $publicSubtitle = 'Community Member';
        if ($user->canActAsLegalRepresentative()) {
            $publicSubtitle = 'Verified Attorney-at-Law';
        } elseif ($profile && !empty($profile->profession_id)) {
            $professionName = $profile->relationLoaded('profession')
                ? $profile->profession?->name
                : \App\Models\Profession::find($profile->profession_id)?->name;
            if ($professionName) {
                $publicSubtitle = $professionName;
            }
        }

        // Safe profile URL identifier for viewing public profile in frontend (/showUserProfile/:id)
        $profileUrl = null;
        if ($profile && $profile->uuid) {
            $profileUrl = "{$profile->slug}-{$profile->uuid}";
        } elseif ($profile && $profile->slug) {
            $profileUrl = $profile->slug;
        }

        return [
            'id' => $user->id,
            'name' => $name,
            'username' => $username,
            'profile_photo_url' => $profilePhotoUrl,
            'public_subtitle' => $publicSubtitle,
            'profile_url' => $profileUrl,
            'is_verified_lawyer' => $user->canActAsLegalRepresentative(),
        ];
    }
}
