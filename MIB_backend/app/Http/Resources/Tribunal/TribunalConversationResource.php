<?php

namespace App\Http\Resources\Tribunal;

use App\Models\TribunalConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TribunalConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TribunalConversation $conv */
        $conv = $this->resource;

        $client = $conv->client;
        $clientName = $client?->profile?->first_name 
            ? "{$client->profile->first_name} {$client->profile->last_name}"
            : ($client?->email ?? 'Client');

        $rep = $conv->representative;
        $repName = $rep?->profile?->first_name 
            ? "{$rep->profile->first_name} {$rep->profile->last_name}"
            : ($rep?->email ?? 'Attorney');

        $currentUserId = auth()->id();
        $unreadCount = $conv->messages()
            ->where('sender_id', '!=', $currentUserId)
            ->whereNull('read_at')
            ->count();

        return [
            'id' => $conv->id,
            'tribunal_case_id' => $conv->tribunal_case_id,
            'case_number' => $conv->tribunalCase?->case_number,
            'type' => $conv->type instanceof \App\Enums\TribunalConversationType ? $conv->type->value : (string) $conv->type,
            'client' => [
                'id' => $client?->id,
                'name' => $clientName,
                'email' => $client?->email,
            ],
            'representative' => [
                'id' => $rep?->id,
                'name' => $repName,
                'email' => $rep?->email,
            ],
            'active' => (bool) $conv->active,
            'unread_count' => $unreadCount,
            'latest_message' => $conv->latestMessage ? new TribunalMessageResource($conv->latestMessage) : null,
            'created_at' => $conv->created_at?->toIso8601String(),
        ];
    }
}
