<?php

namespace Tests\Feature\InternalTribunal;

use App\Enums\InternalPenaltyType;
use App\Enums\InternalReportCategory;
use App\Enums\InternalReportStatus;
use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ExamSession;
use App\Models\InternalPenalty;
use App\Models\InternalReport;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalReport;
use App\Models\User;
use App\Models\profession;
use App\Notifications\InternalTribunal\ReportedUserScorePenaltyNotification;
use App\Services\HipScoreCalculator;
use App\Services\InternalTribunal\InternalPenaltyService;
use App\Services\InternalTribunal\InternalReportService;
use App\Services\ScoreFetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HipScorePenaltyTest extends TestCase
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

    protected function createProfessionalUser(string $email): User
    {
        $user = $this->createUser($email);

        ProfessionalVerification::create([
            'user_id' => $user->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'registration_number' => 'BAR-' . Str::upper(Str::random(6)),
            'issuing_authority' => 'Supreme Court Bar Association',
            'years_of_experience' => 5,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'verified_at' => now(),
            'reviewed_at' => now(),
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

    protected function authHeaders(User $user, ?string &$rawTokenOut = null): array
    {
        $rawToken = 'tok_' . Str::random(40);
        $rawTokenOut = $rawToken;

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
            'category' => InternalReportCategory::AcademicScoreManipulation->value,
            'subject' => 'Score tampering alleged',
            'description' => 'Detailed misconduct report for score penalty test.',
            'severity' => 'high',
        ], [], $reporter);

        $report->update(['status' => InternalReportStatus::Valid->value]);

        return $report->fresh();
    }

    protected function createCompletedExamSession(User $user, float $score, ?\Carbon\Carbon $submittedAt = null): ExamSession
    {
        $submittedAt = $submittedAt ?? now();

        return ExamSession::create([
            'user_id' => $user->id,
            'category_id' => 1,
            'token' => Str::random(32),
            'question_ids' => [1, 2, 3],
            'answers' => ['1' => 'A'],
            'score' => $score,
            'correct_count' => 1,
            'max_score' => $score,
            'status' => ExamSession::STATUS_COMPLETED,
            'started_at' => $submittedAt->copy()->subMinutes(10),
            'expires_at' => $submittedAt->copy()->addMinutes(5),
            'submitted_at' => $submittedAt,
        ]);
    }

    // =========================================================================
    // 1. TARGETING TESTS
    // =========================================================================

    public function test_super_admin_can_apply_hip_penalty_to_normal_user(): void
    {
        $admin = $this->createUser('admin@test.com', true);
        $reporter = $this->createUser('reporter@test.com');
        $target = $this->createUser('target@test.com');
        $report = $this->createValidReport($reporter, $target);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Disciplinary score deduction for academic misconduct.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.action_type', InternalPenaltyType::HipScorePenalty->value);

        $this->assertDatabaseHas('internal_penalties', [
            'internal_report_id' => $report->id,
            'user_id' => $target->id,
            'action_type' => InternalPenaltyType::HipScorePenalty->value,
            'penalty_value' => '1000.00',
            'reversed_at' => null,
        ]);
    }

    public function test_professional_user_can_be_target_of_hip_penalty(): void
    {
        $admin = $this->createUser('admin_pro@test.com', true);
        $reporter = $this->createUser('reporter_pro@test.com');
        $proUser = $this->createProfessionalUser('pro_target@test.com');
        $report = $this->createValidReport($reporter, $proUser);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '500.00',
                'reason' => 'Disciplinary score deduction for verified professional.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('internal_penalties', [
            'user_id' => $proUser->id,
            'action_type' => InternalPenaltyType::HipScorePenalty->value,
            'penalty_value' => '500.00',
        ]);
    }

    public function test_super_admin_cannot_be_target_of_hip_penalty(): void
    {
        $admin = $this->createUser('admin_app@test.com', true);
        $reporter = $this->createUser('reporter_adm@test.com');
        $targetAdmin = $this->createUser('target_admin@test.com', true);
        $report = $this->createValidReport($reporter, $targetAdmin);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Attempting penalty on admin.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('action_type');
    }

    public function test_jury_panel_account_is_immune_from_hip_penalty(): void
    {
        $admin = $this->createUser('admin_jp@test.com', true);
        $reporter = $this->createUser('reporter_jp@test.com');
        [$panel, $panelUser] = $this->createJuryPanel('Test Panel', 'panel_target@test.com');
        $report = $this->createValidReport($reporter, $panelUser);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Attempting score penalty on jury panel.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('action_type');
    }

    public function test_non_admin_cannot_apply_hip_penalty(): void
    {
        $regularUser = $this->createUser('regular@test.com');
        $reporter = $this->createUser('reporter_na@test.com');
        $target = $this->createUser('target_na@test.com');
        $report = $this->createValidReport($reporter, $target);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Unauthorized attempt.',
            ],
            $this->authHeaders($regularUser)
        );

        $response->assertStatus(403);
    }

    public function test_report_must_be_valid_to_apply_hip_penalty(): void
    {
        $admin = $this->createUser('admin_stat@test.com', true);
        $reporter = $this->createUser('reporter_stat@test.com');
        $target = $this->createUser('target_stat@test.com');

        $reportService = app(InternalReportService::class);
        $report = $reportService->createReport([
            'reported_user_id' => $target->id,
            'category' => InternalReportCategory::AcademicScoreManipulation->value,
            'subject' => 'Pending review report',
            'description' => 'Report in UnderReview status.',
            'severity' => 'low',
        ], [], $reporter);

        // Status is UnderReview, not Valid
        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Attempting penalty on non-valid report.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('report');
    }

    // =========================================================================
    // 2. VALIDATION & BOUNDS
    // =========================================================================

    public function test_zero_penalty_value_is_rejected(): void
    {
        $admin = $this->createUser('admin_val0@test.com', true);
        $reporter = $this->createUser('reporter_val0@test.com');
        $target = $this->createUser('target_val0@test.com');
        $report = $this->createValidReport($reporter, $target);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '0',
                'reason' => 'Zero points.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('penalty_value');
    }

    public function test_negative_penalty_value_is_rejected(): void
    {
        $admin = $this->createUser('admin_valneg@test.com', true);
        $reporter = $this->createUser('reporter_valneg@test.com');
        $target = $this->createUser('target_valneg@test.com');
        $report = $this->createValidReport($reporter, $target);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '-500.00',
                'reason' => 'Negative points.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('penalty_value');
    }

    public function test_penalty_value_greater_than_36825_is_rejected(): void
    {
        $admin = $this->createUser('admin_valgt@test.com', true);
        $reporter = $this->createUser('reporter_valgt@test.com');
        $target = $this->createUser('target_valgt@test.com');
        $report = $this->createValidReport($reporter, $target);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '36825.01',
                'reason' => 'Over max bound.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('penalty_value');
    }

    public function test_penalty_value_at_maximum_36825_is_accepted(): void
    {
        $admin = $this->createUser('admin_max@test.com', true);
        $reporter = $this->createUser('reporter_max@test.com');
        $target = $this->createUser('target_max@test.com');
        $report = $this->createValidReport($reporter, $target);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '36825.00',
                'reason' => 'Maximum allowed disciplinary deduction.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('internal_penalties', [
            'penalty_value' => '36825.00',
        ]);
    }

    public function test_decimal_with_up_to_two_places_is_accepted(): void
    {
        $admin = $this->createUser('admin_dec@test.com', true);
        $reporter = $this->createUser('reporter_dec@test.com');
        $target = $this->createUser('target_dec@test.com');
        $report = $this->createValidReport($reporter, $target);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '123.45',
                'reason' => 'Valid two decimals.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('internal_penalties', [
            'penalty_value' => '123.45',
        ]);
    }

    public function test_excessive_decimal_precision_is_rejected(): void
    {
        $admin = $this->createUser('admin_prec@test.com', true);
        $reporter = $this->createUser('reporter_prec@test.com');
        $target = $this->createUser('target_prec@test.com');
        $report = $this->createValidReport($reporter, $target);

        $response = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '100.123',
                'reason' => 'Three decimal places.',
            ],
            $this->authHeaders($admin)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('penalty_value');
    }

    // =========================================================================
    // 3. DUPLICATE ENFORCEMENT
    // =========================================================================

    public function test_same_report_cannot_receive_two_active_hip_penalties(): void
    {
        $admin = $this->createUser('admin_dup@test.com', true);
        $reporter = $this->createUser('reporter_dup@test.com');
        $target = $this->createUser('target_dup@test.com');
        $report = $this->createValidReport($reporter, $target);

        // First penalty succeeds
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '500.00',
                'reason' => 'First penalty from Report A.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // Second penalty from same report fails
        $response2 = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '500.00',
                'reason' => 'Duplicate attempt from Report A.',
            ],
            $this->authHeaders($admin)
        );

        $response2->assertStatus(422)
            ->assertJsonValidationErrors('action_type');
    }

    public function test_different_valid_reports_may_each_apply_separate_penalties(): void
    {
        $admin = $this->createUser('admin_mult@test.com', true);
        $reporter1 = $this->createUser('reporter_m1@test.com');
        $reporter2 = $this->createUser('reporter_m2@test.com');
        $target = $this->createUser('target_mult@test.com');

        $reportA = $this->createValidReport($reporter1, $target);
        $reportB = $this->createValidReport($reporter2, $target);

        // Report A: -500
        $this->postJson(
            "/api/admin/internal-reports/{$reportA->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '500.00',
                'reason' => 'Deduction for Report A violation.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // Report B: -1000
        $this->postJson(
            "/api/admin/internal-reports/{$reportB->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Deduction for Report B violation.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        $this->assertEquals(2, InternalPenalty::where('user_id', $target->id)->whereNull('reversed_at')->count());
    }

    // =========================================================================
    // 4. CALCULATION & HIP INTEGRATION
    // =========================================================================

    public function test_penalty_stored_as_positive_magnitude_and_deducted_as_negative_in_others_legal(): void
    {
        $admin = $this->createUser('admin_calc@test.com', true);
        $reporter = $this->createUser('reporter_calc@test.com');
        $target = $this->createUser('target_calc@test.com');

        // Give target some legitimate base points via completed exam session
        $this->createCompletedExamSession($target, 5000.0);
        HipScoreCalculator::recalculate($target);
        $this->assertEquals(5000.0, (float) $target->fresh()->hip_score);

        $report = $this->createValidReport($reporter, $target);

        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Score deduction.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // Verify database stores positive magnitude
        $penalty = InternalPenalty::where('internal_report_id', $report->id)->first();
        $this->assertEquals('1000.00', $penalty->penalty_value);

        // Breakdown check: others_legal is -1000.00
        $breakdown = HipScoreCalculator::breakdown($target->fresh());
        $this->assertEquals(-1000.0, $breakdown['others_legal']);
        $this->assertEquals(5000.0, $breakdown['em']);

        // Final score check: 5000 - 1000 = 4000
        $this->assertEquals(4000.0, (float) $target->fresh()->hip_score);

        // Repeated recalculate() does not double deduct
        HipScoreCalculator::recalculate($target->fresh());
        $this->assertEquals(4000.0, (float) $target->fresh()->hip_score);

        // hip:recalculate-all retains deduction
        Artisan::call('hip:recalculate-all');
        $this->assertEquals(4000.0, (float) $target->fresh()->hip_score);
    }

    public function test_internal_and_external_penalties_combine_correctly(): void
    {
        $admin = $this->createUser('admin_comb@test.com', true);
        $reporter = $this->createUser('reporter_comb@test.com');
        $target = $this->createUser('target_comb@test.com');

        // Base score: 50000
        $this->createCompletedExamSession($target, 50000.0);

        // External Tribunal penalty: legal conviction (-18412.50)
        TribunalReport::create([
            'user_id' => $target->id,
            'violation_type' => 'legal conviction',
            'details' => 'External tribunal confirmed report',
            'status' => 'confirmed',
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        HipScoreCalculator::recalculate($target);
        // 50000 - 18412.50 = 31587.50
        $this->assertEquals(31587.50, (float) $target->fresh()->hip_score);

        // Now apply Internal Tribunal HIP penalty of 1000.00
        $report = $this->createValidReport($reporter, $target);
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Internal score sanction.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // others_legal: -18412.50 + -1000.00 = -19412.50
        $breakdown = HipScoreCalculator::breakdown($target->fresh());
        $this->assertEquals(-19412.50, $breakdown['others_legal']);

        // Total score: 50000 - 19412.50 = 30587.50
        $this->assertEquals(30587.50, (float) $target->fresh()->hip_score);
    }

    public function test_multiple_internal_penalties_combine_additively(): void
    {
        $admin = $this->createUser('admin_add@test.com', true);
        $reporter1 = $this->createUser('reporter_add1@test.com');
        $reporter2 = $this->createUser('reporter_add2@test.com');
        $target = $this->createUser('target_add@test.com');

        $this->createCompletedExamSession($target, 10000.0);

        $reportA = $this->createValidReport($reporter1, $target);
        $reportB = $this->createValidReport($reporter2, $target);

        // Report A: -500
        $this->postJson(
            "/api/admin/internal-reports/{$reportA->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '500.00',
                'reason' => 'First violation.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // Report B: -1000
        $this->postJson(
            "/api/admin/internal-reports/{$reportB->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Second violation.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        $breakdown = HipScoreCalculator::breakdown($target->fresh());
        $this->assertEquals(-1500.0, $breakdown['others_legal']);
        $this->assertEquals(8500.0, (float) $target->fresh()->hip_score);
    }

    // =========================================================================
    // 5. SCORE DETAILS & PRIVACY PROTECTION
    // =========================================================================

    public function test_score_fetch_service_includes_internal_penalty_with_sanitized_contract(): void
    {
        $admin = $this->createUser('admin_score@test.com', true);
        $reporter = $this->createUser('reporter_score@test.com');
        $target = $this->createUser('target_score@test.com');
        $report = $this->createValidReport($reporter, $target);

        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '750.00',
                'reason' => 'Private internal investigation reason that must not leak.',
                'notes' => 'Top secret administrative notes.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        $penalty = InternalPenalty::where('internal_report_id', $report->id)->first();

        $scoreService = app(ScoreFetchService::class);
        $allScores = $scoreService->getAllScores($target->id);

        $others = $allScores['others'];
        $this->assertNotEmpty($others);

        $penaltyItem = collect($others)->firstWhere('id', 'internal-penalty-' . $penalty->id);
        $this->assertNotNull($penaltyItem);
        $this->assertEquals('Disciplinary Score Deduction', $penaltyItem['title']);
        $this->assertEquals('Administrative tribunal penalty', $penaltyItem['subtitle']);
        $this->assertEquals('confirmed', $penaltyItem['status']);
        $this->assertEquals(-750.00, $penaltyItem['points']);
        $this->assertEquals(now()->toDateString(), $penaltyItem['date']);

        // Verify zero privacy leaks in score history
        $json = json_encode($penaltyItem);
        $this->assertStringNotContainsString('Private internal investigation', $json);
        $this->assertStringNotContainsString('Top secret', $json);
        $this->assertStringNotContainsString($report->report_number, $json);
        $this->assertArrayNotHasKey('reporter', $penaltyItem);
        $this->assertArrayNotHasKey('reporter_id', $penaltyItem);
        $this->assertArrayNotHasKey('user_id', $penaltyItem);
        $this->assertArrayNotHasKey('report_id', $penaltyItem);
        $this->assertArrayNotHasKey('report_number', $penaltyItem);
        $this->assertArrayNotHasKey('reason', $penaltyItem);
        $this->assertArrayNotHasKey('notes', $penaltyItem);
    }

    // =========================================================================
    // 6. LATER SCORE CHANGES CONTINUE NORMALLY
    // =========================================================================

    public function test_later_score_changes_continue_normally_while_penalty_active(): void
    {
        $admin = $this->createUser('admin_later@test.com', true);
        $reporter = $this->createUser('reporter_later@test.com');
        $target = $this->createUser('target_later@test.com');
        $report = $this->createValidReport($reporter, $target);

        // Apply penalty -1000
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Initial penalty.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        $this->assertEquals(-1000.0, (float) $target->fresh()->hip_score);

        // Later: user completes exam for +2500
        $this->createCompletedExamSession($target, 2500.0);
        HipScoreCalculator::recalculate($target);

        // Score is now -1000 + 2500 = 1500
        $this->assertEquals(1500.0, (float) $target->fresh()->hip_score);
    }

    // =========================================================================
    // 7. REVERSAL BEHAVIOR
    // =========================================================================

    public function test_reversing_penalty_removes_deduction_and_preserves_later_earned_points(): void
    {
        $admin = $this->createUser('admin_rev@test.com', true);
        $reporter = $this->createUser('reporter_rev@test.com');
        $target = $this->createUser('target_rev@test.com');
        $report = $this->createValidReport($reporter, $target);

        // Initial exam: 10000
        $this->createCompletedExamSession($target, 10000.0, now()->subHours(2));
        HipScoreCalculator::recalculate($target);
        $this->assertEquals(10000.0, (float) $target->fresh()->hip_score);

        // Penalty -1000 applied -> 9000
        $res = $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'First violation.',
            ],
            $this->authHeaders($admin)
        );
        $penaltyId = $res->json('data.penalty_id');
        $this->assertEquals(9000.0, (float) $target->fresh()->hip_score);

        // Later earned: +500 exam session -> 9500
        $this->createCompletedExamSession($target, 500.0, now()->subHour());
        HipScoreCalculator::recalculate($target);
        $this->assertEquals(9500.0, (float) $target->fresh()->hip_score);

        // Reverse penalty
        $penalty = InternalPenalty::findOrFail($penaltyId);
        $penaltyService = app(InternalPenaltyService::class);
        $penaltyService->reversePenalty($penalty, 'Exonerated upon review', $admin);

        // Recalculation after reversal: 10000 + 500 = 10500 (NOT 10000 restored snapshot)
        $this->assertEquals(10500.0, (float) $target->fresh()->hip_score);

        // Audit metadata check
        $this->assertNotNull($penalty->fresh()->reversed_at);
        $this->assertEquals($admin->id, $penalty->fresh()->reversed_by);
        $this->assertEquals('Exonerated upon review', $penalty->fresh()->reversal_reason);
    }

    public function test_reversing_one_penalty_leaves_other_active_penalties_deducted(): void
    {
        $admin = $this->createUser('admin_rev2@test.com', true);
        $reporter1 = $this->createUser('reporter_r1@test.com');
        $reporter2 = $this->createUser('reporter_r2@test.com');
        $target = $this->createUser('target_rev2@test.com');

        $this->createCompletedExamSession($target, 10000.0);

        $reportA = $this->createValidReport($reporter1, $target);
        $reportB = $this->createValidReport($reporter2, $target);

        // Penalty A: -500
        $resA = $this->postJson(
            "/api/admin/internal-reports/{$reportA->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '500.00',
                'reason' => 'Violation A.',
            ],
            $this->authHeaders($admin)
        );
        $penaltyAId = $resA->json('data.penalty_id');

        // Penalty B: -1000
        $this->postJson(
            "/api/admin/internal-reports/{$reportB->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Violation B.',
            ],
            $this->authHeaders($admin)
        );

        // Total score: 10000 - 1500 = 8500
        $this->assertEquals(8500.0, (float) $target->fresh()->hip_score);

        // Reverse Penalty A only
        $penaltyA = InternalPenalty::findOrFail($penaltyAId);
        app(InternalPenaltyService::class)->reversePenalty($penaltyA, 'Reversing A', $admin);

        // Score should now be 10000 - 1000 = 9000
        $this->assertEquals(9000.0, (float) $target->fresh()->hip_score);
    }

    // =========================================================================
    // 8. NEGATIVE SCORE PRESERVATION (NO ZERO FLOOR)
    // =========================================================================

    public function test_negative_score_is_preserved_and_not_floored_to_zero(): void
    {
        $admin = $this->createUser('admin_neg@test.com', true);
        $reporter = $this->createUser('reporter_neg@test.com');
        $target = $this->createUser('target_neg@test.com');
        $report = $this->createValidReport($reporter, $target);

        // Target starts with 0 score
        HipScoreCalculator::recalculate($target);
        $this->assertEquals(0.0, (float) $target->fresh()->hip_score);

        // Apply 2500 deduction
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '2500.00',
                'reason' => 'Heavy deduction.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        // Score must be -2500.00, NOT 0.00
        $this->assertEquals(-2500.0, (float) $target->fresh()->hip_score);
    }

    // =========================================================================
    // 9. NOTIFICATION & PRIVACY SAFETY
    // =========================================================================

    public function test_sanitized_notification_dispatch_and_transaction_safety(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin_notif@test.com', true);
        $reporter = $this->createUser('reporter_notif@test.com');
        $target = $this->createUser('target_notif@test.com');
        $report = $this->createValidReport($reporter, $target);

        // Successful penalty triggers exactly one notification after commit
        $this->postJson(
            "/api/admin/internal-reports/{$report->id}/penalties",
            [
                'action_type' => InternalPenaltyType::HipScorePenalty->value,
                'penalty_value' => '1000.00',
                'reason' => 'Sensitive confidential misconduct reason.',
                'notes' => 'Classified admin review remarks.',
            ],
            $this->authHeaders($admin)
        )->assertStatus(200);

        Notification::assertSentTo($target, ReportedUserScorePenaltyNotification::class, function ($notif) use ($target, $reporter, $report) {
            $data = $notif->toDatabase($target);

            // Safe content verified
            $this->assertEquals('internal_misconduct_notice', $data['type']);
            $this->assertEquals('HIP / Score Penalty', $data['action_type']);
            $this->assertEquals(1000.0, $data['points_deducted']);
            $this->assertStringContainsString('An administrative deduction of 1,000 points has been applied', $data['message']);

            // Zero leaks verified
            $this->assertArrayNotHasKey('reporter_id', $data);
            $this->assertArrayNotHasKey('report_id', $data);
            $this->assertArrayNotHasKey('report_number', $data);
            $this->assertStringNotContainsString('Sensitive confidential misconduct', $data['message']);
            $this->assertStringNotContainsString('Classified admin review', $data['message']);
            $this->assertStringNotContainsString((string) $reporter->id, $data['message']);
            $this->assertStringNotContainsString($report->report_number, $data['message']);

            return true;
        });

        // Transaction rollback test: no notification on rollback
        Notification::fake();
        $target2 = $this->createUser('target_notif2@test.com');
        $report2 = $this->createValidReport($reporter, $target2);

        try {
            DB::transaction(function () use ($admin, $report2) {
                app(InternalPenaltyService::class)->applyPenalty(
                    $report2,
                    InternalPenaltyType::HipScorePenalty->value,
                    'Rollback reason',
                    null,
                    $admin,
                    null,
                    '500.00'
                );

                throw new \Exception('Intentional rollback');
            });
        } catch (\Exception $e) {
            // Expected
        }

        Notification::assertNotSentTo($target2, ReportedUserScorePenaltyNotification::class);
    }
}
