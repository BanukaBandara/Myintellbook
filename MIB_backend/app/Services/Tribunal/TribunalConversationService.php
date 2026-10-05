<?php

namespace App\Services\Tribunal;

use App\Enums\TribunalConversationType;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Models\TribunalCase;
use App\Models\TribunalConversation;
use App\Models\TribunalMessage;
use App\Models\User;
use App\Notifications\Tribunal\TribunalRepresentativeMessageNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TribunalConversationService
{
    /**
     * Get or create the confidential client-representative conversation for a case.
     */
    public function getOrCreateConversationForUser(TribunalCase $case, int $userId): TribunalConversation
    {
        // 1. Check if user is a case party with an active representative
        $assignmentAsClient = $case->activeRepresentativeAssignments()
            ->where('client_user_id', $userId)
            ->first();

        if ($assignmentAsClient) {
            return TribunalConversation::firstOrCreate(
                [
                    'tribunal_case_id' => $case->id,
                    'client_user_id' => $userId,
                    'representative_user_id' => $assignmentAsClient->representative_user_id,
                ],
                [
                    'type' => $assignmentAsClient->side === TribunalPartyRole::Complainant
                        ? TribunalConversationType::ComplainantRepresentative
                        : TribunalConversationType::RespondentRepresentative,
                    'active' => true,
                ]
            )->load(['client.profile', 'representative.profile', 'tribunalCase']);
        }

        // 2. Check if user is the active representative for a client in this case
        $assignmentAsRep = $case->activeRepresentativeAssignments()
            ->where('representative_user_id', $userId)
            ->first();

        if ($assignmentAsRep) {
            return TribunalConversation::firstOrCreate(
                [
                    'tribunal_case_id' => $case->id,
                    'client_user_id' => $assignmentAsRep->client_user_id,
                    'representative_user_id' => $userId,
                ],
                [
                    'type' => $assignmentAsRep->side === TribunalPartyRole::Complainant
                        ? TribunalConversationType::ComplainantRepresentative
                        : TribunalConversationType::RespondentRepresentative,
                    'active' => true,
                ]
            )->load(['client.profile', 'representative.profile', 'tribunalCase']);
        }

        // 3. User is neither client nor active representative
        abort(403, 'You do not have an active legal representation relationship for this case.');
    }

    /**
     * Get messages in a conversation with strict participant-only access check.
     */
    public function getMessages(TribunalConversation $conversation, int $userId, int $perPage = 50): LengthAwarePaginator
    {
        // Strict boundary: Only client and representative
        abort_unless(
            $conversation->isParticipant($userId),
            403,
            'Access denied. This conversation is confidential between the client and their legal representative.'
        );

        // Mark incoming unread messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $conversation->messages()
            ->with('sender.profile')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    /**
     * Send a message within a private client-representative conversation.
     */
    public function sendMessage(
        TribunalConversation $conversation,
        int $userId,
        string $body,
        ?UploadedFile $attachment = null
    ): TribunalMessage {
        // Strict boundary: Only client and representative
        abort_unless(
            $conversation->isParticipant($userId),
            403,
            'Access denied. You are not authorized to post messages in this conversation.'
        );

        // Active conversation check
        abort_unless(
            $conversation->active,
            403,
            'This representation conversation has been closed.'
        );

        // Active assignment verification
        $hasActiveAssignment = $conversation->tribunalCase->activeRepresentativeAssignments()
            ->where('client_user_id', $conversation->client_user_id)
            ->where('representative_user_id', $conversation->representative_user_id)
            ->exists();

        abort_unless(
            $hasActiveAssignment,
            403,
            'Active legal representation has concluded. No further messages may be sent.'
        );

        return DB::transaction(function () use ($conversation, $userId, $body) {
            $sender = User::findOrFail($userId);

            $message = TribunalMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $userId,
                'body' => $body,
                'attachment_path' => null,
            ]);

            // Notify recipient
            $recipientId = $conversation->client_user_id === $userId 
                ? $conversation->representative_user_id 
                : $conversation->client_user_id;

            $recipient = User::find($recipientId);
            $recipient?->notify(new TribunalRepresentativeMessageNotification(
                $conversation->tribunalCase,
                $conversation,
                $message,
                $sender
            ));

            return $message->load('sender.profile');
        });
    }
}
