<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalMessage $msg */
        $msg = $this->resource;

        $sender = $msg->sender;
        $senderName = $sender?->profile?->first_name 
            ? "{$sender->profile->first_name} {$sender->profile->last_name}"
            : ($sender?->email ?? 'Participant');

        $isMe = auth()->id() === $msg->sender_id;

        return [
            'id' => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'sender_id' => $msg->sender_id,
            'sender_name' => $senderName,
            'is_me' => $isMe,
            'body' => $msg->body,
            'attachment_path' => $msg->attachment_path,
            'read_at' => $msg->read_at?->toIso8601String(),
            'is_read' => $msg->isRead(),
            'created_at' => $msg->created_at?->toIso8601String(),
        ];
    }
}
