<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->user->id,
            'full_name'=>$this->full_name,
            'profile_image'=>$this->profile_image,
            'points'=>$this->user->scores()->sum('points'),
            'hip_score'=>$this->user->hip_score,
            'profile_url'=> $this->slug."-".$this->uuid,
            'rank'=>$this->user->Rank,
            'profile_url'=>$this->full_url,
            'profession'=>optional($this->user->workExperiances->where('currently_working', 1)->first())->title
        ];
    }
}
