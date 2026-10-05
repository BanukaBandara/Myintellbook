<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalCaseMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalCaseMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalCaseMessage $msg */
        $msg = $this->resource;

        $sender = $msg->sender;
        $senderName = $sender?->profile?->first_name
            ? "{$sender->profile->first_name} {$sender->profile->last_name}"
            : ($sender?->email ?? 'Participant');

        $isMe = auth()->id() === $msg->sender_id;

        $roleLabel = match ($msg->sender_case_role) {
            'complainant' => 'Complainant',
            'respondent' => 'Respondent',
            'complainant_representative' => 'Complainant Counsel',
            'respondent_representative' => 'Respondent Counsel',
            'adjudicator' => 'Tribunal Adjudicator',
            default => ucfirst(str_replace('_', ' ', $msg->sender_case_role ?? 'Participant')),
        };

        $evidence = null;
        if ($msg->relatedEvidence) {
            $evidence = [
                'id' => $msg->relatedEvidence->id,
                'evidence_number' => $msg->relatedEvidence->evidence_number,
                'title' => $msg->relatedEvidence->title,
            ];
        }

        return [
            'id' => $msg->id,
            'tribunal_case_room_id' => $msg->tribunal_case_room_id,
            'tribunal_case_id' => $msg->tribunal_case_id,
            'sender_id' => $msg->sender_id,
            'sender_name' => $senderName,
            'sender_case_role' => $msg->sender_case_role,
            'sender_role_label' => $roleLabel,
            'is_me' => $isMe,
            'message_type' => $msg->message_type?->value ?? (string) $msg->message_type,
            'body' => $msg->body,
            'target_side' => $msg->target_side,
            'parent_message_id' => $msg->parent_message_id,
            'related_evidence_id' => $msg->related_evidence_id,
            'related_evidence' => $evidence,
            'procedural' => (bool) $msg->procedural,
            'responses' => TribunalCaseMessageResource::collection($this->whenLoaded('responses')),
            'created_at' => $msg->created_at?->toIso8601String(),
        ];
    }
}
