<?php

namespace Tests\Feature\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\Profile;
use App\Models\TestamentResourceNote;
use App\Models\User;
use App\Models\profession;
use App\Notifications\InternalTribunal\ReportedUserContentRemovalNotification;
use App\Services\InternalTribunal\ContentRemovalService;
use App\Services\InternalTribunal\InternalPenaltyService;
use App\Services\InternalTribunal\InternalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentRemovalPenaltyTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        Storage::fake('local');

        $cat = Category::create(['name' => 'General Category']);
        $prof = profession::create([
            'category_id' => $cat->id,
            'name' => 'Member',
        ]);
        $this->professionId = $prof->id;
    }

    protected function createUser(string $email, bool $isAdmin = false): User
    {
        $suffix = Str::lower(Str::random(6));

        $user = User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'is_admin' => $isAdmin,
        ]);

        Profile::create([
            'user_id' => $user->id,
            'first_name' => "First_{$suffix}",
            'last_name' => "Last_{$suffix}",
            'gender' => 1,
            'profession_id' => $this->professionId,
            'birth_date' => '1990-01-01',
            'slug' => "user_{$suffix}",
        ]);

        return $user;
    }

    protected function createJuryPanel(string $name, string $email): array
    {
        $admin = $this->createUser('admin_jp_' . Str::random(5) . '@test.com', true);
        $service = app(\App\Services\Tribunal\TribunalJuryPanelService::class);

        $panel = $service->createPanel([
            'panel_name' => $name,
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $admin);

        return [$panel, $panel->loginUser];
    }

    protected function authHeaders(User $user): array
    {
        $rawToken = 'tok_' . Str::random(40);

        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'Authorization' => "Bearer {$rawToken}",
            'Accept' => 'application/json',
        ];
    }

    protected function createValidReport(User $reporter, User $reportedUser): InternalReport
    {
        $reportService = app(InternalReportService::class);
        $report = $reportService->createReport([
            'reported_user_id' => $reportedUser->id,
            'category' => InternalReportCategory::ContentCommunityAbuse->value,
            'subject' => 'Offensive resource note reported',
            'description' => 'User posted abusive content in community resource notes.',
            'severity' => 'high',
        ], [], $reporter);

        $report->update(['status' => InternalReportStatus::Valid->value]);

        return $report->fresh();
    }

    protected function createNote(User $user, string $title = 'Community Note Title', string $status = 'active'): TestamentResourceNote
    {
        return TestamentResourceNote::create([
            'user_id' => $user->id,
            'title' => $title,
            'description' => 'A detailed community resource note body with various information.',
            'category' => 'Legal Resources',
            'contact_phone' => '+1-555-0199',
            'contact_email' => 'contact@testamentnote.com',
            'location' => 'Metropolis Central',
            'status' => $status,
        ]);
    }

    public function test_super_admin_can_apply_content_removal_to_valid_report(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($reporter, $reported);

        $note = $this->createNote($reported, 'Offending Post');

        $response = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Offensive terms found in community resource note.',
                'notes' => 'Internal moderator note on content removal.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.action_type', 'Content Removal');

        $this->assertDatabaseHas('internal_penalties', [
            'internal_report_id' => $report->id,
            'user_id' => $reported->id,
            'action_type' => 'Content Removal',
            'penalty_value' => "testament_note:{$note->id}",
            'reason' => 'Offensive terms found in community resource note.',
            'reversed_at' => null,
        ]);

        $this->assertSame(TestamentResourceNote::STATUS_REMOVED, $note->fresh()->status);
        $this->assertSame('Offending Post', $note->fresh()->title);
        $this->assertSame('Legal Resources', $note->fresh()->category);

        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => "Penalty Applied: Content Removal (testament_note:{$note->id})",
        ]);

        Notification::assertSentTo($reported, ReportedUserContentRemovalNotification::class);
    }

    public function test_non_admin_cannot_apply_content_removal(): void
    {
        $nonAdmin = $this->createUser('user@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($nonAdmin, $reported);
        $note = $this->createNote($reported);

        $response = $this->withHeaders($this->authHeaders($nonAdmin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Unauthorized removal attempt.',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_valid_report_is_strictly_required(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($reporter, $reported);
        $note = $this->createNote($reported);

        foreach ([
            InternalReportStatus::Submitted->value,
            InternalReportStatus::UnderReview->value,
            InternalReportStatus::NeedsMoreInformation->value,
            InternalReportStatus::Invalid->value,
            InternalReportStatus::Closed->value,
        ] as $invalidStatus) {
            $report->update(['status' => $invalidStatus]);

            $response = $this->withHeaders($this->authHeaders($admin))->postJson(
                "/api/admin/internal-reports/{$report->id}/penalties",
                [
                    'action_type' => 'Content Removal',
                    'content_type' => 'testament_note',
                    'content_id' => $note->id,
                    'reason' => 'Removal under non-valid status.',
                ]
            );

            $response->assertStatus(422);
        }
    }

    public function test_super_admin_target_is_immune(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $targetAdmin = $this->createUser('target_admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $report = $this->createValidReport($reporter, $targetAdmin);
        $note = $this->createNote($targetAdmin);

        $response = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Targeting an administrator.',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action_type']);
    }

    public function test_jury_panel_target_is_immune(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        [$panel, $panelUser] = $this->createJuryPanel('Regional Judicial Panel', 'panel_target@test.com');
        $reporter = $this->createUser('reporter@test.com');
        $report = $this->createValidReport($reporter, $panelUser);
        $note = $this->createNote($panelUser);

        $response = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Targeting an institutional jury panel account.',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action_type']);
    }

    public function test_unsupported_content_type_or_arbitrary_class_is_rejected(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($reporter, $reported);
        $note = $this->createNote($reported);

        // Unsupported string type
        $response1 = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'comment',
                'content_id' => $note->id,
                'reason' => 'Unsupported content type test.',
            ]
        );
        $response1->assertStatus(422);

        // Arbitrary class
        $response2 = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'App\\Models\\User',
                'content_id' => $reported->id,
                'reason' => 'Arbitrary class test.',
            ]
        );
        $response2->assertStatus(422);
    }

    public function test_nonexistent_or_invalid_content_id_is_rejected(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($reporter, $reported);

        // Nonexistent
        $response1 = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => 999999,
                'reason' => 'Nonexistent note test.',
            ]
        );
        $response1->assertStatus(422);

        // Non-positive integer
        $response2 = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => -10,
                'reason' => 'Negative ID test.',
            ]
        );
        $response2->assertStatus(422);
    }

    public function test_ownership_validation_rejects_content_belonging_to_another_user(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reportedUserA = $this->createUser('reported_a@test.com');
        $innocentUserB = $this->createUser('innocent_b@test.com');

        $report = $this->createValidReport($reporter, $reportedUserA);

        // Innocent user B authored this note
        $noteB = $this->createNote($innocentUserB, 'Innocent User Note');

        $response = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $noteB->id,
                'reason' => 'Attempting to remove another user\'s note.',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_id']);

        $this->assertSame(TestamentResourceNote::STATUS_ACTIVE, $noteB->fresh()->status);
    }

    public function test_already_removed_note_cannot_receive_duplicate_removal(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($reporter, $reported);

        $note = $this->createNote($reported, 'Already Removed Note', TestamentResourceNote::STATUS_REMOVED);

        $response = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Attempting removal on already removed note.',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_id']);
    }

    public function test_unexpected_note_status_is_rejected(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($reporter, $reported);

        $note = $this->createNote($reported, 'Archived Note', 'archived');

        $response = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Attempting removal on archived note.',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_id']);
    }

    public function test_removed_note_is_excluded_from_public_feed_and_author_listing(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $author = $this->createUser('author@test.com');
        $report = $this->createValidReport($reporter, $author);

        $note1 = $this->createNote($author, 'Offensive Note To Remove');
        $note2 = $this->createNote($author, 'Legitimate Note To Keep');

        // Verify both are visible before removal
        $feedBefore = $this->withHeaders($this->authHeaders($author))->getJson('/api/testament/notes');
        $feedBefore->assertOk()
            ->assertJsonFragment(['title' => 'Offensive Note To Remove'])
            ->assertJsonFragment(['title' => 'Legitimate Note To Keep']);

        // Apply Content Removal on note1
        $applyRes = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note1->id,
                'reason' => 'Policy violation in note1.',
            ]
        );
        $applyRes->assertOk();

        // 1. Check Public Feed: note1 is excluded, note2 remains
        $publicFeed = $this->withHeaders($this->authHeaders($reporter))->getJson('/api/testament/notes');
        $publicFeed->assertOk()
            ->assertJsonMissing(['id' => $note1->id, 'title' => 'Offensive Note To Remove'])
            ->assertJsonFragment(['id' => $note2->id, 'title' => 'Legitimate Note To Keep']);

        // 2. Check Author My-Notes: note1 is excluded, note2 remains
        $myNotes = $this->withHeaders($this->authHeaders($author))->getJson('/api/testament/my-notes');
        $myNotes->assertOk()
            ->assertJsonMissing(['id' => $note1->id, 'title' => 'Offensive Note To Remove'])
            ->assertJsonFragment(['id' => $note2->id, 'title' => 'Legitimate Note To Keep']);
    }

    public function test_author_cannot_update_or_delete_removed_note(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $author = $this->createUser('author@test.com');
        $report = $this->createValidReport($reporter, $author);

        $note = $this->createNote($author, 'Offensive Note');

        $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Removed by admin.',
            ]
        )->assertOk();

        // Attempt PUT update by author -> 404
        $updateRes = $this->withHeaders($this->authHeaders($author))->putJson(
            "/api/testament/notes/{$note->id}",
            [
                'title' => 'Sneakily Edited Title',
                'description' => 'Trying to edit removed content.',
                'category' => 'Legal Resources',
            ]
        );
        $updateRes->assertStatus(404);

        // Attempt DELETE by author -> 404
        $deleteRes = $this->withHeaders($this->authHeaders($author))->deleteJson(
            "/api/testament/notes/{$note->id}"
        );
        $deleteRes->assertStatus(404);

        // Confirm database record was not touched or destroyed
        $this->assertDatabaseHas('testament_resource_notes', [
            'id' => $note->id,
            'status' => TestamentResourceNote::STATUS_REMOVED,
            'title' => 'Offensive Note',
        ]);
    }

    public function test_duplicate_active_penalty_for_same_report_and_content_is_prevented(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($reporter, $reported);

        $note = $this->createNote($reported, 'Duplicate Test Note');

        // First application succeeds
        $res1 = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'First removal.',
            ]
        );
        $res1->assertOk();

        // Second application fails
        $res2 = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Duplicate attempt.',
            ]
        );
        $res2->assertStatus(422);
    }

    public function test_multiple_different_notes_can_be_removed_independently_under_same_report(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $reported = $this->createUser('reported@test.com');
        $report = $this->createValidReport($reporter, $reported);

        $noteA = $this->createNote($reported, 'Offending Note A');
        $noteB = $this->createNote($reported, 'Offending Note B');

        // Apply on note A
        $resA = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $noteA->id,
                'reason' => 'Violation in Note A.',
            ]
        );
        $resA->assertOk();

        // Apply on note B
        $resB = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $noteB->id,
                'reason' => 'Violation in Note B.',
            ]
        );
        $resB->assertOk();

        $this->assertSame(TestamentResourceNote::STATUS_REMOVED, $noteA->fresh()->status);
        $this->assertSame(TestamentResourceNote::STATUS_REMOVED, $noteB->fresh()->status);
    }

    public function test_reversal_restores_note_to_active_and_updates_visibility(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $author = $this->createUser('author@test.com');
        $report = $this->createValidReport($reporter, $author);

        $note = $this->createNote($author, 'Restoration Test Note');

        $applyRes = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Mistakenly flagged content.',
            ]
        );
        $applyRes->assertOk();
        $penaltyId = $applyRes->json('data.penalty_id');

        $this->assertSame(TestamentResourceNote::STATUS_REMOVED, $note->fresh()->status);

        // Reverse the penalty
        $reverseRes = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/penalties/{$penaltyId}/reverse",
            [
                'reversal_reason' => 'Content review determined note is within guidelines.',
            ]
        );
        $reverseRes->assertOk();

        // Verify note is active again
        $this->assertSame(TestamentResourceNote::STATUS_ACTIVE, $note->fresh()->status);

        // Verify note is back in public feed
        $feed = $this->withHeaders($this->authHeaders($reporter))->getJson('/api/testament/notes');
        $feed->assertOk()
            ->assertJsonFragment(['id' => $note->id, 'title' => 'Restoration Test Note']);

        // Verify penalty record has reversal metadata
        $penalty = InternalPenalty::find($penaltyId);
        $this->assertNotNull($penalty->reversed_at);
        $this->assertSame($admin->id, $penalty->reversed_by);
        $this->assertSame('Content review determined note is within guidelines.', $penalty->reversal_reason);

        // Verify audit log recorded reversal
        $this->assertDatabaseHas('internal_report_audits', [
            'internal_report_id' => $report->id,
            'action' => "Penalty Reversed: Content Removal (testament_note:{$note->id})",
        ]);
    }

    public function test_reversal_only_restores_selected_note(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $author = $this->createUser('author@test.com');
        $report = $this->createValidReport($reporter, $author);

        $note1 = $this->createNote($author, 'Note One');
        $note2 = $this->createNote($author, 'Note Two');

        $res1 = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note1->id,
                'reason' => 'Remove note 1.',
            ]
        );
        $pen1Id = $res1->json('data.penalty_id');

        $res2 = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note2->id,
                'reason' => 'Remove note 2.',
            ]
        );
        $pen2Id = $res2->json('data.penalty_id');

        // Reverse ONLY note 1
        $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/penalties/{$pen1Id}/reverse",
            [
                'reversal_reason' => 'Restoring note 1 only.',
            ]
        )->assertOk();

        $this->assertSame(TestamentResourceNote::STATUS_ACTIVE, $note1->fresh()->status);
        $this->assertSame(TestamentResourceNote::STATUS_REMOVED, $note2->fresh()->status);
    }

    public function test_reversal_safety_rejects_if_note_is_not_in_removed_status(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $author = $this->createUser('author@test.com');
        $report = $this->createValidReport($reporter, $author);

        $note = $this->createNote($author, 'Safety Check Note');

        $applyRes = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => 'Content Removal',
                'content_type' => 'testament_note',
                'content_id' => $note->id,
                'reason' => 'Removing note.',
            ]
        );
        $penaltyId = $applyRes->json('data.penalty_id');

        // Manually tamper status to something else (e.g. 'archived')
        $note->update(['status' => 'archived']);

        $reverseRes = $this->withHeaders($this->authHeaders($admin))->postJson(
            "/api/admin/internal-reports/penalties/{$penaltyId}/reverse",
            [
                'reversal_reason' => 'Reversal should fail because status is not removed.',
            ]
        );

        $reverseRes->assertStatus(500);

        // Penalty must NOT be marked reversed
        $penalty = InternalPenalty::find($penaltyId);
        $this->assertNull($penalty->reversed_at);
        $this->assertSame('archived', $note->fresh()->status);
    }

    public function test_sanitized_notification_payload_does_not_leak_private_data(): void
    {
        $notification = new ReportedUserContentRemovalNotification();
        $payload = $notification->toDatabase(new User());

        $this->assertSame('internal_misconduct_notice', $payload['type']);
        $this->assertSame('Content Removal', $payload['action_type']);
        $this->assertSame('Community Resource Note', $payload['content_category']);
        $this->assertStringContainsString('A community resource note posted from your account has been removed', $payload['message']);

        // Security assertions: ensure no confidential report/reporter/audit fields
        $this->assertArrayNotHasKey('reporter_id', $payload);
        $this->assertArrayNotHasKey('report_number', $payload);
        $this->assertArrayNotHasKey('evidence', $payload);
        $this->assertArrayNotHasKey('admin_notes', $payload);
        $this->assertArrayNotHasKey('reason', $payload);
    }
}
