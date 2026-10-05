<?php

namespace App\Http\Controllers;

use App\Models\Testament;
use App\Models\TestamentAuditLog;
use App\Models\TestamentResourceNote;
use App\Models\User;
use App\Notifications\NewUserNotification;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Fixed Arm for Testament Management (FATM): a member's testament, its witness and the
 * append-only audit/consent log.
 *
 * Lifecycle: draft → awaiting_witness → sealed (then awaits Central Tribunal review).
 * The owner can recall a submission back to draft, or withdraw at any stage.
 */
class TestamentController extends Controller
{
    public const CONSENT_STATEMENT = 'I consent to MyIntellibook FATM storing this testament in encrypted form and releasing it only upon verified activation.';
    public const ATTESTATION_STATEMENT = 'I confirm that I witnessed the testator declare this testament as their own, of sound mind and free will.';

    public function publicFeed(): JsonResponse
    {
        $notes = TestamentResourceNote::query()
            ->with('user.profile')
            ->where('status', TestamentResourceNote::STATUS_ACTIVE)
            ->latest()
            ->get()
            ->map(fn (TestamentResourceNote $note) => [
                'id' => $note->id,
                'title' => $note->title,
                'description' => $note->description,
                'category' => $note->category,
                'phone' => $note->contact_phone,
                'email' => $note->contact_email,
                'location' => $note->location,
                'status' => $note->status,
                'created_at' => $note->created_at?->toIso8601String(),
                'owner_name' => $this->displayName($note->user),
            ])
            ->values();

        return response()->json(['success' => true, 'notes' => $notes]);
    }

    public function createResourceNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:160'],
        ]);

        $note = TestamentResourceNote::query()->create([
            'user_id' => $this->userId($request),
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'category' => trim($data['category']),
            'contact_phone' => $data['phone'] ?? null,
            'contact_email' => $data['email'] ?? null,
            'location' => isset($data['location']) ? trim($data['location']) : null,
            'status' => TestamentResourceNote::STATUS_ACTIVE,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your resource note is now shared with the community.',
            'note' => [
                'id' => $note->id,
                'title' => $note->title,
                'description' => $note->description,
                'category' => $note->category,
                'phone' => $note->contact_phone,
                'email' => $note->contact_email,
                'location' => $note->location,
                'status' => $note->status,
                'created_at' => $note->created_at?->toIso8601String(),
                'owner_name' => $this->displayName($request->user()),
            ],
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $testament = $this->current($userId);

        return response()->json([
            'success' => true,
            'testament' => $testament ? $this->ownerPayload($testament) : null,
            'consent_statement' => self::CONSENT_STATEMENT,
            'pending_witness_requests' => Testament::query()
                ->where('witness_user_id', $userId)
                ->where('status', Testament::STATUS_AWAITING_WITNESS)
                ->where('witness_status', Testament::WITNESS_PENDING)
                ->count(),
        ]);
    }

    /** Creates the draft, or updates it while it is still a draft. */
    public function save(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $data = $request->validate($this->draftRules($userId));

        $testament = DB::transaction(function () use ($userId, $data) {
            User::query()->lockForUpdate()->findOrFail($userId);
            $testament = $this->current($userId, lock: true);

            if ($testament && !$testament->isEditable()) {
                return null;
            }

            $isNew = !$testament;
            $testament ??= new Testament(['user_id' => $userId, 'status' => Testament::STATUS_DRAFT]);

            $changed = [];
            foreach (['title', 'instructions', 'health_declaration', 'beneficiaries', 'witness_user_id'] as $field) {
                $key = $field === 'health_declaration' ? 'health' : $field;
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $value = $field === 'health_declaration' ? $this->normalizeHealth($data[$key]) : $data[$key];
                if ($field === 'beneficiaries' && is_array($value)) {
                    $value = array_values(array_map(fn ($b) => [
                        'name' => trim($b['name']),
                        'relationship' => trim($b['relationship']),
                        'share' => round((float) $b['share'], 2),
                    ], $value));
                }
                if ($isNew || $testament->{$field} != $value) {
                    $testament->{$field} = $value;
                    $changed[] = $key;
                }
            }

            $testament->content_hash = $testament->computeContentHash();
            $testament->save();

            if ($isNew) {
                TestamentAuditLog::record($testament, 'draft_created', $userId);
            } elseif ($changed !== []) {
                // Field names and counts only — never the content itself.
                TestamentAuditLog::record($testament, 'draft_updated', $userId, array_filter([
                    'fields' => $changed,
                    'beneficiary_count' => in_array('beneficiaries', $changed, true) ? count($testament->beneficiaries ?? []) : null,
                ], fn ($value) => $value !== null));
            }

            return $testament;
        });

        if (!$testament) {
            return $this->locked();
        }

        return response()->json(['success' => true, 'message' => 'Draft saved securely.', 'testament' => $this->ownerPayload($testament->fresh())]);
    }

    /** Records consent and asks the chosen witness to confirm. */
    public function submit(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $request->validate(['consent' => ['required', 'accepted']]);

        $result = DB::transaction(function () use ($userId) {
            $testament = $this->current($userId, lock: true);
            if (!$testament) {
                return ['status' => 'missing'];
            }
            if (!$testament->isEditable()) {
                return ['status' => 'locked'];
            }
            if ($problems = $this->readinessProblems($testament, $userId)) {
                return ['status' => 'incomplete', 'problems' => $problems];
            }

            $testament->fill([
                'status' => Testament::STATUS_AWAITING_WITNESS,
                'witness_status' => Testament::WITNESS_PENDING,
                'witness_responded_at' => null,
                'consent_given_at' => now(),
                'content_hash' => $testament->computeContentHash(),
            ])->save();

            TestamentAuditLog::record($testament, 'consent_given', $userId, ['statement' => self::CONSENT_STATEMENT]);
            TestamentAuditLog::record($testament, 'witness_requested', $userId, ['witness_user_id' => $testament->witness_user_id]);

            return ['status' => 'submitted', 'testament' => $testament];
        });

        if ($result['status'] === 'missing') {
            return response()->json(['success' => false, 'message' => 'Save a draft first.'], 404);
        }
        if ($result['status'] === 'locked') {
            return $this->locked();
        }
        if ($result['status'] === 'incomplete') {
            return response()->json(['success' => false, 'message' => 'Your testament is not ready to submit.', 'problems' => $result['problems']], 422);
        }

        $testament = $result['testament'];
        $testament->witness?->notify(new NewUserNotification(
            $this->displayName($testament->user).' has asked you to witness their testament. Open Testament Management to respond.'
        ));

        return response()->json(['success' => true, 'message' => 'Sent to your witness for confirmation.', 'testament' => $this->ownerPayload($testament->fresh())]);
    }

    /** Pulls a pending submission back to draft so it can be edited. */
    public function recall(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        $testament = DB::transaction(function () use ($userId) {
            $testament = $this->current($userId, lock: true);
            if (!$testament || $testament->status !== Testament::STATUS_AWAITING_WITNESS) {
                return null;
            }
            $testament->fill(['status' => Testament::STATUS_DRAFT, 'witness_status' => null])->save();
            TestamentAuditLog::record($testament, 'submission_recalled', $userId);

            return $testament;
        });

        if (!$testament) {
            return response()->json(['success' => false, 'message' => 'There is no pending submission to recall.'], 409);
        }

        return response()->json(['success' => true, 'testament' => $this->ownerPayload($testament->fresh())]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $request->validate(['confirm' => ['required', 'accepted']]);

        $testament = DB::transaction(function () use ($userId) {
            $testament = $this->current($userId, lock: true);
            if (!$testament) {
                return null;
            }
            $testament->fill(['status' => Testament::STATUS_WITHDRAWN, 'withdrawn_at' => now()])->save();
            TestamentAuditLog::record($testament, 'withdrawn', $userId);

            return $testament;
        });

        if (!$testament) {
            return response()->json(['success' => false, 'message' => 'You have no testament to withdraw.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Your testament has been withdrawn.']);
    }

    /** Testaments this user has been asked to witness. Content is never shown to the witness. */
    public function witnessRequests(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        $requests = Testament::query()
            ->with('user.profile')
            ->where('witness_user_id', $userId)
            ->where('status', Testament::STATUS_AWAITING_WITNESS)
            ->where('witness_status', Testament::WITNESS_PENDING)
            ->orderBy('consent_given_at')
            ->get()
            ->map(fn (Testament $testament) => [
                'id' => $testament->id,
                'testator' => $this->displayName($testament->user),
                'requested_at' => $testament->consent_given_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'attestation_statement' => self::ATTESTATION_STATEMENT,
            'requests' => $requests,
        ]);
    }

    public function witnessRespond(Request $request, Testament $testament, string $decision): JsonResponse
    {
        $userId = $this->userId($request);
        if ($decision === 'confirm') {
            $request->validate(['attestation' => ['required', 'accepted']]);
        }

        $result = DB::transaction(function () use ($testament, $userId, $decision) {
            $testament = Testament::query()->lockForUpdate()->find($testament->id);
            if (!$testament
                || $testament->witness_user_id !== $userId
                || $testament->status !== Testament::STATUS_AWAITING_WITNESS
                || $testament->witness_status !== Testament::WITNESS_PENDING) {
                return null;
            }

            if ($decision === 'decline') {
                $testament->fill([
                    'status' => Testament::STATUS_DRAFT,
                    'witness_status' => Testament::WITNESS_DECLINED,
                    'witness_responded_at' => now(),
                ])->save();
                TestamentAuditLog::record($testament, 'witness_declined', $userId);

                return $testament;
            }

            // The content must be byte-for-byte what was submitted before it can be sealed.
            if (!hash_equals((string) $testament->content_hash, $testament->computeContentHash())) {
                TestamentAuditLog::record($testament, 'integrity_check_failed', $userId);

                return 'integrity';
            }

            $testament->fill([
                'status' => Testament::STATUS_SEALED,
                'witness_status' => Testament::WITNESS_CONFIRMED,
                'witness_responded_at' => now(),
                'sealed_at' => now(),
                'tribunal_status' => Testament::TRIBUNAL_AWAITING_REVIEW,
            ])->save();
            TestamentAuditLog::record($testament, 'witness_confirmed', $userId, ['statement' => self::ATTESTATION_STATEMENT]);
            TestamentAuditLog::record($testament, 'sealed', null, ['content_hash' => $testament->content_hash]);

            return $testament;
        });

        if ($result === 'integrity') {
            return response()->json(['success' => false, 'message' => 'This testament failed its integrity check and cannot be sealed.'], 409);
        }
        if (!$result) {
            return response()->json(['success' => false, 'message' => 'This witness request is no longer open.'], 404);
        }

        $witnessName = $this->displayName(User::query()->with('profile')->find($userId));
        $result->user?->notify(new NewUserNotification($decision === 'confirm'
            ? "{$witnessName} confirmed your testament. It is now sealed and awaiting Central Tribunal review."
            : "{$witnessName} declined to witness your testament. It is back in draft so you can choose another witness."));

        return response()->json(['success' => true, 'message' => $decision === 'confirm' ? 'Thank you — the testament is now sealed.' : 'You declined this request.']);
    }

    // ---------------------------------------------------------------------------------------

    private function userId(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    private function current(int $userId, bool $lock = false): ?Testament
    {
        return Testament::query()
            ->where('user_id', $userId)
            ->where('status', '!=', Testament::STATUS_WITHDRAWN)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->latest('id')
            ->first();
    }

    private function draftRules(int $userId): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'health' => ['sometimes', 'nullable', 'array'],
            'health.sound_mind' => ['sometimes', 'boolean'],
            'health.no_duress' => ['sometimes', 'boolean'],
            'health.physician_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'health.assessment_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'health.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'beneficiaries' => ['sometimes', 'array', 'max:20'],
            'beneficiaries.*.name' => ['required', 'string', 'max:120'],
            'beneficiaries.*.relationship' => ['required', 'string', 'max:60'],
            'beneficiaries.*.share' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'witness_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id'), Rule::notIn([$userId])],
        ];
    }

    private function normalizeHealth(?array $health): array
    {
        return [
            'sound_mind' => (bool) ($health['sound_mind'] ?? false),
            'no_duress' => (bool) ($health['no_duress'] ?? false),
            'physician_name' => $health['physician_name'] ?? null,
            'assessment_date' => $health['assessment_date'] ?? null,
            'notes' => $health['notes'] ?? null,
        ];
    }

    /** @return string[] human-readable reasons the testament can't be submitted yet */
    private function readinessProblems(Testament $testament, int $userId): array
    {
        $problems = [];
        if (trim((string) $testament->title) === '') {
            $problems[] = 'Add a title.';
        }
        if (trim((string) $testament->instructions) === '') {
            $problems[] = 'Write your instructions.';
        }
        $health = $testament->health_declaration ?? [];
        if (empty($health['sound_mind']) || empty($health['no_duress'])) {
            $problems[] = 'Complete the health declaration.';
        }
        $beneficiaries = $testament->beneficiaries ?? [];
        if ($beneficiaries === []) {
            $problems[] = 'Add at least one beneficiary.';
        } elseif (abs(array_sum(array_column($beneficiaries, 'share')) - 100) > 0.01) {
            $problems[] = 'Beneficiary shares must add up to 100%.';
        }
        if (!$testament->witness_user_id || $testament->witness_user_id === $userId) {
            $problems[] = 'Choose a witness.';
        }

        return $problems;
    }

    private function ownerPayload(Testament $testament): array
    {
        $testament->loadMissing('witness.profile', 'auditLogs.actor.profile');

        // Decrypting proves the stored ciphertext is intact and readable with the current key.
        try {
            $content = [
                'title' => $testament->title,
                'instructions' => $testament->instructions,
                'health' => $testament->health_declaration,
                'beneficiaries' => $testament->beneficiaries ?? [],
            ];
            $encryptionOk = true;
        } catch (DecryptException) {
            $content = ['title' => null, 'instructions' => null, 'health' => null, 'beneficiaries' => []];
            $encryptionOk = false;
        }

        $contentHashOk = $encryptionOk && $testament->content_hash !== null
            && hash_equals($testament->content_hash, $testament->computeContentHash());

        return [
            'id' => $testament->id,
            'status' => $testament->status,
            ...$content,
            'witness' => $testament->witness ? [
                'id' => $testament->witness->id,
                'name' => $this->displayName($testament->witness),
                'status' => $testament->witness_status,
                'responded_at' => $testament->witness_responded_at?->toIso8601String(),
            ] : null,
            'tribunal_status' => $testament->tribunal_status,
            'consent_given_at' => $testament->consent_given_at?->toIso8601String(),
            'sealed_at' => $testament->sealed_at?->toIso8601String(),
            'updated_at' => $testament->updated_at?->toIso8601String(),
            'content_hash' => $testament->content_hash,
            'verification' => [
                'encrypted_at_rest' => $encryptionOk,
                'content_hash_matches' => $contentHashOk,
                'audit_chain_intact' => TestamentAuditLog::verifyChain($testament->id),
            ],
            'audit' => $testament->auditLogs->map(fn (TestamentAuditLog $entry) => [
                'id' => $entry->id,
                'event' => $entry->event,
                'actor' => $entry->actor ? $this->displayName($entry->actor) : 'System',
                'details' => $entry->details,
                'hash' => $entry->hash,
                'created_at' => \Illuminate\Support\Carbon::parse($entry->getRawOriginal('created_at'))->toIso8601String(),
            ])->values(),
        ];
    }

    private function displayName(?User $user): string
    {
        return $user?->profile?->full_name ?: 'A member';
    }

    private function locked(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This testament has been submitted and can no longer be edited. Recall or withdraw it first.',
        ], 409);
    }
}
