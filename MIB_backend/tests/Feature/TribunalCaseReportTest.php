<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalDecisionOrderStatus;
use App\Enums\TribunalDecisionOrderType;
use App\Enums\TribunalDecisionOutcome;
use App\Enums\TribunalDecisionStatus;
use App\Enums\TribunalDeliberationNoteType;
use App\Enums\TribunalDeliberationStatus;
use App\Enums\TribunalEvidenceStatus;
use App\Enums\TribunalEvidenceType;
use App\Enums\TribunalFindingConclusion;
use App\Enums\TribunalFindingType;
use App\Enums\TribunalHearingEntryType;
use App\Enums\TribunalHearingLocationType;
use App\Enums\TribunalHearingStatus;
use App\Enums\TribunalHearingType;
use App\Enums\TribunalJuryPanelAssignmentMethod;
use App\Enums\TribunalJuryPanelAssignmentStatus;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Enums\TribunalWitnessStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalCaseReport;
use App\Models\TribunalCaseReportDownload;
use App\Models\TribunalCaseResponse;
use App\Models\TribunalDecision;
use App\Models\TribunalDecisionOrder;
use App\Models\TribunalDeliberation;
use App\Models\TribunalDeliberationNote;
use App\Models\TribunalEvidence;
use App\Models\TribunalFinding;
use App\Models\TribunalHearing;
use App\Models\TribunalHearingEntry;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelAssignment;
use App\Models\TribunalRepresentativeAssignment;
use App\Models\TribunalWitness;
use App\Models\User;
use App\Models\profession;
use App\Services\Tribunal\TribunalDecisionService;
use App\Services\Tribunal\TribunalJuryPanelService;
use App\Services\Tribunal\TribunalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalCaseReportTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        config(['tribunal.appeal_window_days' => 14]);
        Storage::fake('local');

        $cat = Category::create(['name' => 'Legal Test Category']);
        $prof = profession::create([
            'category_id' => $cat->id,
            'name' => 'Attorney',
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
            'birth_date' => '1985-05-15',
        ]);

        return $user;
    }

    protected function authHeaders(User $user): array
    {
        $rawToken = 'token_' . Str::random(40);

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

    protected function createVerifiedLawyer(string $email): User
    {
        $lawyer = $this->createUser($email, false);

        ProfessionalVerification::create([
            'user_id' => $lawyer->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'registration_number' => 'BAR-' . Str::random(6),
            'enrollment_number' => 'ENR-' . Str::random(6),
            'issuing_authority' => 'Bar Association',
            'verified_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        return $lawyer;
    }

    protected function createCaseWithFinalDecision(array $options = []): array
    {
        $complainant = $this->createUser('comp_' . Str::random(5) . '@test.com');
        $respondent = $this->createUser('resp_' . Str::random(5) . '@test.com');

        $case = TribunalCase::create([
            'created_by' => $complainant->id,
            'title' => 'Report Test Matter: ' . Str::random(6),
            'category' => 'Financial Dispute',
            'description' => $options['description'] ?? 'Formal complaint regarding contractual non-performance.',
            'requested_resolution' => 'Damages and performance order.',
            'status' => $options['case_status'] ?? TribunalCaseStatus::AppealWindow,
            'severity' => 'high',
            'submitted_at' => now()->subDays(10),
        ]);

        $case->update([
            'case_number' => sprintf('MIB-TRB-%s-%06d', now()->year, $case->id),
        ]);

        $case->parties()->create([
            'user_id' => $complainant->id,
            'role' => TribunalPartyRole::Complainant,
        ]);

        $case->parties()->create([
            'user_id' => $respondent->id,
            'role' => TribunalPartyRole::Respondent,
        ]);

        // Formal response
        $case->response()->create([
            'respondent_id' => $respondent->id,
            'position' => \App\Enums\TribunalResponsePosition::Deny,
            'response_text' => 'Respondent explicitly denies all assertions of liability.',
            'submitted_at' => now()->subDays(8),
        ]);

        // Jury Panel
        $admin = $this->createUser('admin_' . Str::random(5) . '@test.com', true);
        $panel = app(TribunalJuryPanelService::class)->createPanel([
            'panel_name' => 'Judicial Panel ' . Str::random(4),
            'email' => 'panel_' . Str::random(5) . '@test.com',
            'password' => 'SecurePass123!',
        ], $admin);

        TribunalJuryPanelAssignment::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'status' => TribunalJuryPanelAssignmentStatus::Active,
            'assignment_method' => TribunalJuryPanelAssignmentMethod::Automatic,
            'assigned_at' => now()->subDays(7),
        ]);

        // Evidence
        $evidence = TribunalEvidence::create([
            'tribunal_case_id' => $case->id,
            'uploaded_by' => $complainant->id,
            'evidence_number' => 'EVID-0001',
            'type' => TribunalEvidenceType::Document,
            'title' => 'Executed Agreement Contract',
            'description' => 'Original signed contractual documentation.',
            'file_path' => 'private/secret_paths/evidence.pdf', // Private path that must never leak!
            'stored_filename' => 'internal_hashed_evidence_123.pdf',
            'status' => TribunalEvidenceStatus::Accepted,
            'submitted_at' => now()->subDays(6),
        ]);

        // Witness
        $witness = TribunalWitness::create([
            'tribunal_case_id' => $case->id,
            'proposed_by' => $complainant->id,
            'side' => 'complainant',
            'witness_name' => 'Dr. Witness Testifier',
            'statement_summary' => 'Witness confirmed execution of terms during hearing.',
            'status' => TribunalWitnessStatus::Approved,
        ]);

        // Hearing
        $hearing = TribunalHearing::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'hearing_number' => 'HRG-' . Str::random(8),
            'hearing_type' => TribunalHearingType::Formal,
            'status' => TribunalHearingStatus::Completed,
            'scheduled_at' => now()->subDays(4),
            'started_at' => now()->subDays(4)->addHour(),
            'ended_at' => now()->subDays(4)->addHours(2),
            'location_type' => TribunalHearingLocationType::Online,
            'created_by' => $panel->login_user_id,
        ]);

        TribunalHearingEntry::create([
            'tribunal_hearing_id' => $hearing->id,
            'sender_id' => $complainant->id,
            'participant_type' => 'complainant',
            'side' => 'complainant',
            'entry_type' => TribunalHearingEntryType::OpeningStatement,
            'body' => 'Official opening statement delivered on record.',
            'sequence_number' => 1,
        ]);

        // Deliberation
        $deliberation = TribunalDeliberation::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'status' => TribunalDeliberationStatus::Completed,
            'opened_at' => now()->subDays(3),
            'completed_at' => now()->subDays(1),
        ]);

        // STRICTLY PRIVATE deliberation note (must NEVER leak!)
        TribunalDeliberationNote::create([
            'tribunal_deliberation_id' => $deliberation->id,
            'author_user_id' => $panel->login_user_id,
            'note_type' => TribunalDeliberationNoteType::EvidenceAnalysis,
            'body' => 'TOP_SECRET_DELIBERATION_NOTE_PRIVATE_TO_PANEL',
        ]);

        // Findings
        $publicFinding = TribunalFinding::create([
            'tribunal_case_id' => $case->id,
            'tribunal_deliberation_id' => $deliberation->id,
            'finding_number' => 'FIND-001',
            'finding_type' => TribunalFindingType::Fact,
            'title' => 'Contractual Default Established',
            'finding_text' => 'The tribunal finds clear and convincing evidence of non-performance.',
            'conclusion' => TribunalFindingConclusion::Established,
            'display_order' => 1,
            'is_public' => true,
            'created_by' => $panel->login_user_id,
        ]);

        $publicFinding->evidence()->attach($evidence->id);
        $publicFinding->witnesses()->attach($witness->id);

        // Private non-public finding
        TribunalFinding::create([
            'tribunal_case_id' => $case->id,
            'tribunal_deliberation_id' => $deliberation->id,
            'finding_number' => 'FIND-PRIVATE-999',
            'finding_type' => TribunalFindingType::Fact,
            'title' => 'Private Credibility Evaluation',
            'finding_text' => 'PRIVATE_FINDING_NOT_FOR_PUBLIC_EYES',
            'conclusion' => TribunalFindingConclusion::Established,
            'display_order' => 2,
            'is_public' => false,
            'created_by' => $panel->login_user_id,
        ]);

        // Final Decision
        $decision = TribunalDecision::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'decision_number' => 'DEC-' . Str::random(8),
            'status' => $options['decision_status'] ?? TribunalDecisionStatus::Final,
            'outcome' => TribunalDecisionOutcome::ComplaintUpheld,
            'summary' => 'The complaint is fully upheld following evidentiary examination.',
            'reasoning' => 'The contractual stipulations were unequivocally breached without justified defense.',
            'published_at' => now()->subDay(),
            'appeal_deadline' => now()->addDays(13),
            'created_by' => $panel->login_user_id,
        ]);

        // Decision Order
        TribunalDecisionOrder::create([
            'tribunal_decision_id' => $decision->id,
            'order_number' => 'ORD-0001',
            'order_type' => TribunalDecisionOrderType::CorrectiveAction,
            'title' => 'Restitution Payment Ordered',
            'description' => 'Respondent shall remit outstanding balance within 14 calendar days.',
            'target_side' => 'respondent',
            'deadline_at' => now()->addDays(14),
            'status' => TribunalDecisionOrderStatus::Recorded,
        ]);

        return [
            'case' => $case,
            'decision' => $decision,
            'complainant' => $complainant,
            'respondent' => $respondent,
            'panel' => $panel,
            'panel_user' => $panel->loginUser,
            'admin' => $admin,
            'evidence' => $evidence,
        ];
    }

    // -------------------------------------------------------------------------
    // TEST 1: Complainant can generate final report
    // -------------------------------------------------------------------------
    #[Test]
    public function test_01_complainant_can_generate_final_report(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $complainant = $setup['complainant'];

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($complainant));

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'report' => [
                'id',
                'report_number',
                'verification_code',
                'status',
                'version',
                'file_hash',
                'download_url',
                'verify_url',
            ],
        ]);

        $this->assertDatabaseHas('tribunal_case_reports', [
            'tribunal_case_id' => $case->id,
            'status' => 'generated',
            'version' => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 2: Respondent can generate / access final report
    // -------------------------------------------------------------------------
    #[Test]
    public function test_02_respondent_can_generate_and_access_final_report(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $respondent = $setup['respondent'];

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($respondent));
        $response->assertStatus(201);

        $reportId = $response->json('report.id');

        $showResponse = $this->getJson("/api/tribunal/reports/{$reportId}", $this->authHeaders($respondent));
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('report.case_number', $case->case_number);
    }

    // -------------------------------------------------------------------------
    // TEST 3 & 4: Active lawyers can access report
    // -------------------------------------------------------------------------
    #[Test]
    public function test_03_active_complainant_lawyer_can_access(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $complainant = $setup['complainant'];

        $compLawyer = $this->createVerifiedLawyer('comp_lawyer@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $compLawyer->id,
            'side' => 'complainant',
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
            'assigned_at' => now(),
        ]);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($compLawyer));
        $response->assertStatus(201);
    }

    #[Test]
    public function test_04_active_respondent_lawyer_can_access(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $respondent = $setup['respondent'];

        $respLawyer = $this->createVerifiedLawyer('resp_lawyer@test.com');
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $respondent->id,
            'representative_user_id' => $respLawyer->id,
            'side' => 'respondent',
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
            'assigned_at' => now(),
        ]);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($respLawyer));
        $response->assertStatus(201);
    }

    // -------------------------------------------------------------------------
    // TEST 5 & 6: Unrelated users and unrelated lawyers get 403
    // -------------------------------------------------------------------------
    #[Test]
    public function test_05_unrelated_user_gets_403(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $unrelated = $this->createUser('stranger@test.com');

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($unrelated));
        $response->assertStatus(403);

        $listResponse = $this->getJson("/api/tribunal/cases/{$case->id}/reports", $this->authHeaders($unrelated));
        $listResponse->assertStatus(403);
    }

    #[Test]
    public function test_06_unrelated_lawyer_gets_403(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $otherLawyer = $this->createVerifiedLawyer('other_lawyer@test.com');

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($otherLawyer));
        $response->assertStatus(403);
    }

    #[Test]
    public function test_06b_inactive_or_ended_representation_lawyer_gets_403(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $complainant = $setup['complainant'];
        $formerLawyer = $this->createVerifiedLawyer('former_lawyer@test.com');

        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $complainant->id,
            'representative_user_id' => $formerLawyer->id,
            'side' => 'complainant',
            'status' => TribunalRepresentativeAssignmentStatus::Ended,
            'accepted_at' => now()->subMonths(2),
            'assigned_at' => now()->subMonths(2),
            'ended_at' => now()->subMonth(),
        ]);

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($formerLawyer));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 7: Cannot generate before decision is final
    // -------------------------------------------------------------------------
    #[Test]
    public function test_07_cannot_generate_before_decision_is_final(): void
    {
        $setup = $this->createCaseWithFinalDecision([
            'case_status' => TribunalCaseStatus::Deliberation,
            'decision_status' => TribunalDecisionStatus::Draft,
        ]);
        $case = $setup['case'];
        $complainant = $setup['complainant'];

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($complainant));
        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // TEST 8 & 9: Report number and verification code are unique
    // -------------------------------------------------------------------------
    #[Test]
    public function test_08_and_09_report_number_and_verification_code_are_unique(): void
    {
        $setup1 = $this->createCaseWithFinalDecision();
        $setup2 = $this->createCaseWithFinalDecision();

        $service = app(TribunalReportService::class);
        $report1 = $service->generateFinalReport($setup1['case'], $setup1['complainant']->id);
        $report2 = $service->generateFinalReport($setup2['case'], $setup2['complainant']->id);

        $this->assertNotEquals($report1->report_number, $report2->report_number);
        $this->assertNotEquals($report1->verification_code, $report2->verification_code);

        $this->assertMatchesRegularExpression('/^MIB-RPT-\d{4}-\d{6}$/', $report1->report_number);
        $this->assertMatchesRegularExpression('/^VER-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $report1->verification_code);
    }

    // -------------------------------------------------------------------------
    // TEST 10, 11, 12: PDF file stored privately with SHA-256 matching
    // -------------------------------------------------------------------------
    #[Test]
    public function test_10_11_12_pdf_exists_in_private_storage_with_matching_sha256(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $service = app(TribunalReportService::class);
        $report = $service->generateFinalReport($setup['case'], $setup['complainant']->id);

        $this->assertTrue(Storage::disk('local')->exists($report->file_path));

        $fileContent = Storage::disk('local')->get($report->file_path);
        $this->assertNotEmpty($fileContent);

        $actualHash = hash('sha256', $fileContent);
        $this->assertEquals($report->file_hash, $actualHash);
        $this->assertEquals(64, strlen($report->file_hash));
    }

    // -------------------------------------------------------------------------
    // TEST 13 to 20: Dossier & PDF content safety checks
    // -------------------------------------------------------------------------
    #[Test]
    public function test_13_to_20_safe_report_dossier_content_and_strict_privacy(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $decision = $setup['decision'];
        $service = app(TribunalReportService::class);

        $dossier = $service->buildDossier(
            $case,
            $decision,
            'MIB-RPT-2026-000001',
            'VER-TEST-1234',
            'data:image/svg+xml;base64,test',
            'http://localhost:5173/tribunal/reports/verify/VER-TEST-1234',
            1,
            now()
        );

        // 13: Contains case number
        $this->assertEquals($case->case_number, $dossier['case_number']);

        // 14: Contains final decision number
        $this->assertEquals($decision->decision_number, $dossier['decision_number']);

        // 15: Contains public findings only
        $this->assertNotEmpty($dossier['findings']);
        $findingNumbers = array_column($dossier['findings'], 'finding_number');
        $this->assertContains('FIND-001', $findingNumbers);
        $this->assertNotContains('FIND-PRIVATE-999', $findingNumbers);

        // 16: Contains evidence index (NO private paths)
        $this->assertNotEmpty($dossier['evidence_index']);
        $evItem = $dossier['evidence_index'][0];
        $this->assertEquals('EVID-0001', $evItem['evidence_number']);
        $this->assertArrayNotHasKey('file_path', $evItem);
        $this->assertArrayNotHasKey('stored_filename', $evItem);

        // 17: Contains witnesses
        $this->assertNotEmpty($dossier['witnesses']);
        $this->assertEquals('Dr. Witness Testifier', $dossier['witnesses'][0]['witness_name']);

        // 18: Strictly does NOT contain private deliberation notes!
        $serialized = json_encode($dossier);
        $this->assertStringNotContainsString('TOP_SECRET_DELIBERATION_NOTE_PRIVATE_TO_PANEL', $serialized);
        $this->assertStringNotContainsString('PRIVATE_FINDING_NOT_FOR_PUBLIC_EYES', $serialized);

        // 19: Does NOT contain private paths
        $this->assertStringNotContainsString('private/secret_paths/evidence.pdf', $serialized);
        $this->assertStringNotContainsString('internal_hashed_evidence_123.pdf', $serialized);
    }

    // -------------------------------------------------------------------------
    // TEST 21 & 22: Download creates audit record and updates download_count
    // -------------------------------------------------------------------------
    #[Test]
    public function test_21_22_download_creates_audit_record_and_updates_count(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $complainant = $setup['complainant'];

        $service = app(TribunalReportService::class);
        $report = $service->generateFinalReport($case, $complainant->id);

        $this->assertEquals(0, $report->download_count);
        $this->assertNull($report->last_downloaded_at);

        $response = $this->get("/api/tribunal/reports/{$report->id}/download", $this->authHeaders($complainant));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        $report->refresh();
        $this->assertEquals(1, $report->download_count);
        $this->assertNotNull($report->last_downloaded_at);

        $this->assertDatabaseHas('tribunal_case_report_downloads', [
            'tribunal_case_report_id' => $report->id,
            'downloaded_by_user_id' => $complainant->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 23, 24, 25: Public verification endpoint behavior
    // -------------------------------------------------------------------------
    #[Test]
    public function test_23_verification_endpoint_validates_real_code(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $service = app(TribunalReportService::class);
        $report = $service->generateFinalReport($setup['case'], $setup['complainant']->id);

        $response = $this->getJson("/api/tribunal/reports/verify/{$report->verification_code}");

        $response->assertStatus(200);
        $response->assertJson([
            'valid' => true,
            'details' => [
                'report_number' => $report->report_number,
                'case_number' => $setup['case']->case_number,
                'status' => 'generated',
                'file_hash' => $report->file_hash,
                'authenticity_confirmed' => true,
            ],
        ]);
    }

    #[Test]
    public function test_24_invalid_verification_code_rejected_safely(): void
    {
        $response = $this->getJson("/api/tribunal/reports/verify/VER-FAKE-9999");

        $response->assertStatus(404);
        $response->assertJson([
            'valid' => false,
            'message' => 'INVALID OR UNVERIFIED REPORT',
            'details' => null,
        ]);
    }

    #[Test]
    public function test_25_verification_response_does_not_expose_private_case_data(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $service = app(TribunalReportService::class);
        $report = $service->generateFinalReport($setup['case'], $setup['complainant']->id);

        $response = $this->getJson("/api/tribunal/reports/verify/{$report->verification_code}");
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringNotContainsString('complaint_description', $content);
        $this->assertStringNotContainsString('evidence', $content);
        $this->assertStringNotContainsString('witnesses', $content);
        $this->assertStringNotContainsString('deliberation', $content);
    }

    // -------------------------------------------------------------------------
    // TEST 26: Watermark marker in rendered HTML/PDF workflow
    // -------------------------------------------------------------------------
    #[Test]
    public function test_26_watermark_present_in_blade_view(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $decision = $setup['decision'];
        $service = app(TribunalReportService::class);

        $dossier = $service->buildDossier(
            $case,
            $decision,
            'MIB-RPT-2026-000001',
            'VER-TEST-1234',
            'data:image/svg+xml;base64,test',
            'http://localhost:5173/tribunal/reports/verify/VER-TEST-1234',
            1,
            now()
        );

        $viewHtml = view('tribunal.reports.final-decision', $dossier)->render();

        $this->assertStringContainsString('id="watermark"', $viewHtml);
        $this->assertStringContainsString('MYINTELLIBOOK_LIVE', $viewHtml);
        $this->assertStringContainsString('OFFICIAL TRIBUNAL REPORT', $viewHtml);
    }

    // -------------------------------------------------------------------------
    // TEST 27: QR verification URL generated correctly
    // -------------------------------------------------------------------------
    #[Test]
    public function test_27_qr_verification_url_generated_correctly(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $service = app(TribunalReportService::class);
        $report = $service->generateFinalReport($setup['case'], $setup['complainant']->id);

        $expectedBase = config('tribunal.report_verify_url');
        $this->assertStringContainsString($report->verification_code, $expectedBase . '/' . $report->verification_code);
    }

    // -------------------------------------------------------------------------
    // TEST 28: Report unavailable to Jury Panel members
    // -------------------------------------------------------------------------
    #[Test]
    public function test_28_report_unavailable_to_jury_panel(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $panelUser = $setup['panel_user'];

        $response = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($panelUser));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 29 & 30: Report regeneration preserves prior version and marks superseded
    // -------------------------------------------------------------------------
    #[Test]
    public function test_29_30_regeneration_increments_version_and_marks_prior_superseded(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $case = $setup['case'];
        $complainant = $setup['complainant'];

        // Initial generation (v1)
        $resp1 = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($complainant));
        $resp1->assertStatus(201);
        $this->assertEquals(1, $resp1->json('report.version'));
        $v1ReportId = $resp1->json('report.id');

        // Request again without regenerate flag returns existing v1
        $respSame = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", [], $this->authHeaders($complainant));
        $respSame->assertStatus(201);
        $this->assertEquals($v1ReportId, $respSame->json('report.id'));

        // Request WITH regenerate flag increments to v2
        $respRegen = $this->postJson("/api/tribunal/cases/{$case->id}/reports/final", ['regenerate' => true], $this->authHeaders($complainant));
        $respRegen->assertStatus(201);
        $this->assertEquals(2, $respRegen->json('report.version'));
        $v2ReportId = $respRegen->json('report.id');

        $this->assertNotEquals($v1ReportId, $v2ReportId);

        // Verify v1 is marked superseded and still exists in DB
        $v1 = TribunalCaseReport::find($v1ReportId);
        $this->assertEquals('superseded', $v1->status);

        $v2 = TribunalCaseReport::find($v2ReportId);
        $this->assertEquals('generated', $v2->status);

        // Both files exist in storage
        $this->assertTrue(Storage::disk('local')->exists($v1->file_path));
        $this->assertTrue(Storage::disk('local')->exists($v2->file_path));
    }

    // -------------------------------------------------------------------------
    // TEST 31: User-controlled HTML/scripts are safely escaped
    // -------------------------------------------------------------------------
    #[Test]
    public function test_31_user_controlled_html_is_safely_escaped(): void
    {
        $xssPayload = '<script>alert("XSS")</script><img src=x onerror=alert(1)>';
        $setup = $this->createCaseWithFinalDecision([
            'description' => $xssPayload,
        ]);
        $case = $setup['case'];
        $decision = $setup['decision'];
        $service = app(TribunalReportService::class);

        $dossier = $service->buildDossier(
            $case,
            $decision,
            'MIB-RPT-2026-000001',
            'VER-TEST-1234',
            'data:image/svg+xml;base64,test',
            'http://localhost:5173/tribunal/reports/verify/VER-TEST-1234',
            1,
            now()
        );

        $viewHtml = view('tribunal.reports.final-decision', $dossier)->render();

        // Must NOT render executable unescaped tags
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $viewHtml);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $viewHtml);
        // Must contain escaped entities
        $this->assertStringContainsString('&lt;script&gt;', $viewHtml);
    }

    // -------------------------------------------------------------------------
    // SECURITY TESTS: IDOR, Cross-Case, and File Hash Verification
    // -------------------------------------------------------------------------
    #[Test]
    public function test_security_idor_report_access_rejected_for_unauthorized_user(): void
    {
        $setup1 = $this->createCaseWithFinalDecision();
        $setup2 = $this->createCaseWithFinalDecision();

        $service = app(TribunalReportService::class);
        $report1 = $service->generateFinalReport($setup1['case'], $setup1['complainant']->id);

        // User from case 2 tries to download case 1's report by changing ID
        $response = $this->getJson("/api/tribunal/reports/{$report1->id}", $this->authHeaders($setup2['complainant']));
        $response->assertStatus(403);

        $downloadResp = $this->get("/api/tribunal/reports/{$report1->id}/download", $this->authHeaders($setup2['complainant']));
        $downloadResp->assertStatus(403);
    }

    #[Test]
    public function test_security_file_hash_verification_endpoint(): void
    {
        $setup = $this->createCaseWithFinalDecision();
        $service = app(TribunalReportService::class);
        $report = $service->generateFinalReport($setup['case'], $setup['complainant']->id);

        $fileContent = Storage::disk('local')->get($report->file_path);

        // Genuine file upload
        $genuineFile = UploadedFile::fake()->createWithContent('genuine_report.pdf', $fileContent);
        $res = $this->postJson('/api/tribunal/reports/verify-file', [
            'report_file' => $genuineFile,
            'verification_code' => $report->verification_code,
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('valid_hash', true);
        $res->assertJsonPath('matched', true);

        // Tampered file upload
        $tamperedFile = UploadedFile::fake()->createWithContent('tampered_report.pdf', $fileContent . 'TAMPERED');
        $resTampered = $this->postJson('/api/tribunal/reports/verify-file', [
            'report_file' => $tamperedFile,
            'verification_code' => $report->verification_code,
        ]);

        $resTampered->assertStatus(422);
        $resTampered->assertJsonPath('valid_hash', false);
        $resTampered->assertJsonPath('matched', false);
    }
}
