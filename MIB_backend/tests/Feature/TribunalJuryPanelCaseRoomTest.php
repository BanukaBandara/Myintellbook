<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalCaseMessageType;
use App\Enums\TribunalCaseStatus;
use App\Enums\TribunalConversationType;
use App\Enums\TribunalJuryPanelAssignmentMethod;
use App\Enums\TribunalJuryPanelAssignmentStatus;
use App\Enums\TribunalJuryPanelStatus;
use App\Enums\TribunalPartyRole;
use App\Enums\TribunalRepresentativeAssignmentStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalCaseMessage;
use App\Models\TribunalConversation;
use App\Models\TribunalJuryAssignment;
use App\Models\TribunalJuryPanel;
use App\Models\TribunalJuryPanelAssignment;
use App\Models\TribunalMessage;
use App\Models\TribunalRepresentativeAssignment;
use App\Models\User;
use App\Models\profession;
use App\Services\Tribunal\TribunalCaseRoomService;
use App\Services\Tribunal\TribunalJuryPanelService;
use App\Services\Tribunal\TribunalMediationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalJuryPanelCaseRoomTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);

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

    protected function createJuryPanel(string $panelName, string $email, string $status = 'active'): array
    {
        $admin = $this->createUser('admin_' . Str::random(5) . '@test.com', true);
        $service = app(TribunalJuryPanelService::class);

        $panel = $service->createPanel([
            'panel_name' => $panelName,
            'email' => $email,
            'password' => 'SecurePass123!',
        ], $admin);

        if ($status !== 'active') {
            $panel->status = $status;
            $panel->save();
        }

        return [$panel, $panel->loginUser];
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

    protected function createCase(User $complainant, User $respondent, string $status = 'jury_selection'): TribunalCase
    {
        $case = TribunalCase::create([
            'created_by' => $complainant->id,
            'title' => 'Commercial Contract Dispute ' . Str::random(5),
            'category' => 'Commercial',
            'description' => 'Dispute regarding SLA breaches and delayed fulfillment.',
            'requested_resolution' => 'Contractual damages.',
            'status' => $status,
            'severity' => 'high',
            'submitted_at' => now(),
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

        return $case;
    }

    protected function assignPanelToCase(TribunalJuryPanel $panel, TribunalCase $case): TribunalJuryPanelAssignment
    {
        return TribunalJuryPanelAssignment::create([
            'tribunal_case_id' => $case->id,
            'tribunal_jury_panel_id' => $panel->id,
            'status' => TribunalJuryPanelAssignmentStatus::Active,
            'assignment_method' => TribunalJuryPanelAssignmentMethod::Automatic,
            'assigned_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 1: Assigned Jury Panel can access shared case room
    // -------------------------------------------------------------------------
    #[Test]
    public function test_01_assigned_jury_panel_can_access_shared_case_room(): void
    {
        $comp = $this->createUser('comp1@test.com');
        $resp = $this->createUser('resp1@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Alpha', 'jp1@panel.test');
        $this->assignPanelToCase($panel, $case);

        // Through Jury endpoint
        $resJury = $this->getJson("/api/jury/cases/{$case->id}/case-room/messages", $this->authHeaders($panelUser));
        $resJury->assertStatus(200);

        // Through shared tribunal endpoint
        $resTribunal = $this->getJson("/api/tribunal/cases/{$case->id}/case-room/messages", $this->authHeaders($panelUser));
        $resTribunal->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // TEST 2: Unassigned Jury Panel cannot access shared case room
    // -------------------------------------------------------------------------
    #[Test]
    public function test_02_unassigned_jury_panel_cannot_access_shared_case_room(): void
    {
        $comp = $this->createUser('comp2@test.com');
        $resp = $this->createUser('resp2@test.com');
        $case = $this->createCase($comp, $resp);
        [$assignedPanel, $assignedUser] = $this->createJuryPanel('Assigned Panel', 'assigned@panel.test');
        $this->assignPanelToCase($assignedPanel, $case);

        [$otherPanel, $otherUser] = $this->createJuryPanel('Other Panel', 'other@panel.test');

        // Jury endpoint -> 404
        $this->getJson("/api/jury/cases/{$case->id}/case-room/messages", $this->authHeaders($otherUser))
            ->assertStatus(404);

        // Tribunal endpoint -> 403
        $this->getJson("/api/tribunal/cases/{$case->id}/case-room/messages", $this->authHeaders($otherUser))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 3: Inactive Jury Panel cannot access shared case room
    // -------------------------------------------------------------------------
    #[Test]
    public function test_03_inactive_jury_panel_cannot_access_shared_case_room(): void
    {
        $comp = $this->createUser('comp3@test.com');
        $resp = $this->createUser('resp3@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Inactive Panel', 'inactive@panel.test', 'inactive');
        $this->assignPanelToCase($panel, $case);

        // Blocked by jury.panel middleware or 403
        $this->getJson("/api/jury/cases/{$case->id}/case-room/messages", $this->authHeaders($panelUser))
            ->assertStatus(403);

        $this->getJson("/api/tribunal/cases/{$case->id}/case-room/messages", $this->authHeaders($panelUser))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 4: Normal unrelated user cannot access shared case room
    // -------------------------------------------------------------------------
    #[Test]
    public function test_04_normal_user_unrelated_cannot_access_shared_case_room(): void
    {
        $comp = $this->createUser('comp4@test.com');
        $resp = $this->createUser('resp4@test.com');
        $unrelated = $this->createUser('unrelated@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel 4', 'jp4@panel.test');
        $this->assignPanelToCase($panel, $case);

        $this->getJson("/api/tribunal/cases/{$case->id}/case-room/messages", $this->authHeaders($unrelated))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 5: Assigned Jury Panel can send normal shared message
    // -------------------------------------------------------------------------
    #[Test]
    public function test_05_assigned_jury_panel_can_send_shared_message(): void
    {
        $comp = $this->createUser('comp5@test.com');
        $resp = $this->createUser('resp5@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Five', 'jp5@panel.test');
        $this->assignPanelToCase($panel, $case);

        $res = $this->postJson("/api/jury/cases/{$case->id}/case-room/messages", [
            'body' => 'Jury Panel has reviewed the latest evidence submission.',
        ], $this->authHeaders($panelUser));

        $res->assertStatus(201);
        $res->assertJsonPath('data.sender_case_role', 'jury_panel');
        $res->assertJsonPath('data.sender_role_label', 'Tribunal Jury Panel');
        $this->assertStringContainsString($panel->panel_code, $res->json('data.sender_name'));

        $this->assertDatabaseHas('tribunal_case_messages', [
            'tribunal_case_id' => $case->id,
            'sender_id' => $panelUser->id,
            'sender_case_role' => 'jury_panel',
            'body' => 'Jury Panel has reviewed the latest evidence submission.',
        ]);

        $this->assertDatabaseHas('tribunal_case_events', [
            'tribunal_case_id' => $case->id,
            'event_type' => 'jury_panel_case_room_message_posted',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 6: Assigned Jury Panel can post procedural notice
    // -------------------------------------------------------------------------
    #[Test]
    public function test_06_assigned_jury_panel_can_post_procedural_notice(): void
    {
        $comp = $this->createUser('comp6@test.com');
        $resp = $this->createUser('resp6@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Six', 'jp6@panel.test');
        $this->assignPanelToCase($panel, $case);

        $res = $this->postJson("/api/jury/cases/{$case->id}/case-room/procedural-notices", [
            'body' => 'Both parties must complete remaining evidence submissions before 10 October 2026.',
        ], $this->authHeaders($panelUser));

        $res->assertStatus(201);
        $res->assertJsonPath('data.message_type', 'procedural_notice');
        $res->assertJsonPath('data.sender_role_label', 'Tribunal Jury Panel');

        $this->assertDatabaseHas('tribunal_case_events', [
            'tribunal_case_id' => $case->id,
            'event_type' => 'jury_panel_procedural_notice_posted',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 7: Normal complainant cannot post procedural notice
    // -------------------------------------------------------------------------
    #[Test]
    public function test_07_normal_complainant_cannot_post_procedural_notice(): void
    {
        $comp = $this->createUser('comp7@test.com');
        $resp = $this->createUser('resp7@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Seven', 'jp7@panel.test');
        $this->assignPanelToCase($panel, $case);

        $this->postJson("/api/tribunal/cases/{$case->id}/case-room/procedural-notices", [
            'body' => 'Unauthorized procedural notice attempt.',
        ], $this->authHeaders($comp))->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 8: Lawyer cannot post procedural notice
    // -------------------------------------------------------------------------
    #[Test]
    public function test_08_lawyer_cannot_post_procedural_notice(): void
    {
        $comp = $this->createUser('comp8@test.com');
        $resp = $this->createUser('resp8@test.com');
        $lawyer = $this->createVerifiedLawyer('lawyer8@bar.test');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Eight', 'jp8@panel.test');
        $this->assignPanelToCase($panel, $case);

        // Assign lawyer to complainant
        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $comp->id,
            'representative_user_id' => $lawyer->id,
            'side' => 'complainant',
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
            'assigned_at' => now(),
        ]);

        $this->postJson("/api/tribunal/cases/{$case->id}/case-room/procedural-notices", [
            'body' => 'Lawyer posting procedural notice.',
        ], $this->authHeaders($lawyer))->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 9: Assigned Jury Panel can ask complainant targeted question
    // -------------------------------------------------------------------------
    #[Test]
    public function test_09_assigned_jury_panel_can_ask_complainant_question(): void
    {
        $comp = $this->createUser('comp9@test.com');
        $resp = $this->createUser('resp9@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Nine', 'jp9@panel.test');
        $this->assignPanelToCase($panel, $case);

        $res = $this->postJson("/api/jury/cases/{$case->id}/case-room/questions", [
            'body' => 'Complainant, please confirm the total amount claimed for damages.',
            'target_side' => 'complainant',
        ], $this->authHeaders($panelUser));

        $res->assertStatus(201);
        $res->assertJsonPath('data.target_side', 'complainant');
        $res->assertJsonPath('data.sender_role_label', 'Tribunal Jury Panel');

        $this->assertDatabaseHas('tribunal_case_events', [
            'tribunal_case_id' => $case->id,
            'event_type' => 'jury_panel_question_posted',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 10: Assigned Jury Panel can ask respondent targeted question
    // -------------------------------------------------------------------------
    #[Test]
    public function test_10_assigned_jury_panel_can_ask_respondent_question(): void
    {
        $comp = $this->createUser('comp10@test.com');
        $resp = $this->createUser('resp10@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Ten', 'jp10@panel.test');
        $this->assignPanelToCase($panel, $case);

        $res = $this->postJson("/api/jury/cases/{$case->id}/case-room/questions", [
            'body' => 'Respondent, please confirm the date of the disputed communication.',
            'target_side' => 'respondent',
        ], $this->authHeaders($panelUser));

        $res->assertStatus(201);
        $res->assertJsonPath('data.target_side', 'respondent');
    }

    // -------------------------------------------------------------------------
    // TEST 11: Assigned Jury Panel can ask both parties question
    // -------------------------------------------------------------------------
    #[Test]
    public function test_11_assigned_jury_panel_can_ask_both_parties_question(): void
    {
        $comp = $this->createUser('comp11@test.com');
        $resp = $this->createUser('resp11@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Eleven', 'jp11@panel.test');
        $this->assignPanelToCase($panel, $case);

        $res = $this->postJson("/api/jury/cases/{$case->id}/case-room/questions", [
            'body' => 'Both parties, please specify whether any mediation took place prior to filing.',
            'target_side' => 'both',
        ], $this->authHeaders($panelUser));

        $res->assertStatus(201);
        $res->assertJsonPath('data.target_side', 'both');
    }

    // -------------------------------------------------------------------------
    // TEST 12: Complainant can respond to targeted question
    // -------------------------------------------------------------------------
    #[Test]
    public function test_12_complainant_can_respond_to_targeted_question(): void
    {
        $comp = $this->createUser('comp12@test.com');
        $resp = $this->createUser('resp12@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Twelve', 'jp12@panel.test');
        $this->assignPanelToCase($panel, $case);

        $qRes = $this->postJson("/api/jury/cases/{$case->id}/case-room/questions", [
            'body' => 'Complainant, specify claim breakdown.',
            'target_side' => 'complainant',
        ], $this->authHeaders($panelUser));

        $questionId = $qRes->json('data.id');

        $res = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/questions/{$questionId}/responses", [
            'body' => 'Claim consists of $50,000 direct damages and $10,000 expenses.',
        ], $this->authHeaders($comp));

        $res->assertStatus(201);
        $res->assertJsonPath('data.parent_message_id', $questionId);
        $res->assertJsonPath('data.message_type', 'question_response');
    }

    // -------------------------------------------------------------------------
    // TEST 13: Respondent can respond to targeted question
    // -------------------------------------------------------------------------
    #[Test]
    public function test_13_respondent_can_respond_to_targeted_question(): void
    {
        $comp = $this->createUser('comp13@test.com');
        $resp = $this->createUser('resp13@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Thirteen', 'jp13@panel.test');
        $this->assignPanelToCase($panel, $case);

        $qRes = $this->postJson("/api/jury/cases/{$case->id}/case-room/questions", [
            'body' => 'Respondent, confirm delivery date.',
            'target_side' => 'respondent',
        ], $this->authHeaders($panelUser));

        $questionId = $qRes->json('data.id');

        $res = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/questions/{$questionId}/responses", [
            'body' => 'The event occurred on 20 September 2026.',
        ], $this->authHeaders($resp));

        $res->assertStatus(201);
        $res->assertJsonPath('data.parent_message_id', $questionId);
    }

    // -------------------------------------------------------------------------
    // TEST 14: Active representative response preserves real sender identity
    // -------------------------------------------------------------------------
    #[Test]
    public function test_14_active_representative_response_preserves_real_sender_identity(): void
    {
        $comp = $this->createUser('comp14@test.com');
        $resp = $this->createUser('resp14@test.com');
        $lawyer = $this->createVerifiedLawyer('counsel14@bar.test');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Fourteen', 'jp14@panel.test');
        $this->assignPanelToCase($panel, $case);

        TribunalRepresentativeAssignment::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $resp->id,
            'representative_user_id' => $lawyer->id,
            'side' => 'respondent',
            'status' => TribunalRepresentativeAssignmentStatus::Active,
            'accepted_at' => now(),
            'assigned_at' => now(),
        ]);

        $qRes = $this->postJson("/api/jury/cases/{$case->id}/case-room/questions", [
            'body' => 'Respondent, provide legal basis.',
            'target_side' => 'respondent',
        ], $this->authHeaders($panelUser));

        $questionId = $qRes->json('data.id');

        $res = $this->postJson("/api/tribunal/cases/{$case->id}/case-room/questions/{$questionId}/responses", [
            'body' => 'Responded by Attorney on behalf of Respondent.',
        ], $this->authHeaders($lawyer));

        $res->assertStatus(201);
        $res->assertJsonPath('data.sender_id', $lawyer->id);
        $res->assertJsonPath('data.sender_case_role', 'respondent_representative');
        $this->assertNotEquals($resp->id, $res->json('data.sender_id'));
    }

    // -------------------------------------------------------------------------
    // TEST 15: Jury Panel can see question responses in shared room
    // -------------------------------------------------------------------------
    #[Test]
    public function test_15_jury_panel_can_see_question_responses(): void
    {
        $comp = $this->createUser('comp15@test.com');
        $resp = $this->createUser('resp15@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Fifteen', 'jp15@panel.test');
        $this->assignPanelToCase($panel, $case);

        $qRes = $this->postJson("/api/jury/cases/{$case->id}/case-room/questions", [
            'body' => 'Respondent question for verification.',
            'target_side' => 'respondent',
        ], $this->authHeaders($panelUser));

        $questionId = $qRes->json('data.id');

        $this->postJson("/api/tribunal/cases/{$case->id}/case-room/questions/{$questionId}/responses", [
            'body' => 'Confirmed by respondent.',
        ], $this->authHeaders($resp))->assertStatus(201);

        $roomRes = $this->getJson("/api/jury/cases/{$case->id}/case-room/messages", $this->authHeaders($panelUser));
        $roomRes->assertStatus(200);

        $messages = collect($roomRes->json('data'));
        $parentQ = $messages->firstWhere('id', $questionId);
        $this->assertNotNull($parentQ);
        $this->assertNotEmpty($parentQ['responses']);
        $this->assertEquals('Confirmed by respondent.', $parentQ['responses'][0]['body']);
    }

    // -------------------------------------------------------------------------
    // TEST 16: Jury Panel cannot access complainant-lawyer private chat
    // -------------------------------------------------------------------------
    #[Test]
    public function test_16_jury_panel_cannot_access_complainant_lawyer_private_chat(): void
    {
        $comp = $this->createUser('comp16@test.com');
        $resp = $this->createUser('resp16@test.com');
        $lawyer = $this->createVerifiedLawyer('lawyer16@bar.test');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Sixteen', 'jp16@panel.test');
        $this->assignPanelToCase($panel, $case);

        $conversation = TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $comp->id,
            'representative_user_id' => $lawyer->id,
            'type' => TribunalConversationType::ComplainantRepresentative,
        ]);

        $this->getJson("/api/tribunal/conversations/{$conversation->id}/messages", $this->authHeaders($panelUser))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 17: Jury Panel cannot access respondent-lawyer private chat
    // -------------------------------------------------------------------------
    #[Test]
    public function test_17_jury_panel_cannot_access_respondent_lawyer_private_chat(): void
    {
        $comp = $this->createUser('comp17@test.com');
        $resp = $this->createUser('resp17@test.com');
        $lawyer = $this->createVerifiedLawyer('lawyer17@bar.test');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Seventeen', 'jp17@panel.test');
        $this->assignPanelToCase($panel, $case);

        $conversation = TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $resp->id,
            'representative_user_id' => $lawyer->id,
            'type' => TribunalConversationType::RespondentRepresentative,
        ]);

        $this->getJson("/api/tribunal/conversations/{$conversation->id}/messages", $this->authHeaders($panelUser))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 18: Jury Panel cannot send message to private lawyer-client conversation
    // -------------------------------------------------------------------------
    #[Test]
    public function test_18_jury_panel_cannot_send_message_to_private_conversation(): void
    {
        $comp = $this->createUser('comp18@test.com');
        $resp = $this->createUser('resp18@test.com');
        $lawyer = $this->createVerifiedLawyer('lawyer18@bar.test');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Eighteen', 'jp18@panel.test');
        $this->assignPanelToCase($panel, $case);

        $conversation = TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $comp->id,
            'representative_user_id' => $lawyer->id,
            'type' => TribunalConversationType::ComplainantRepresentative,
        ]);

        $this->postJson("/api/tribunal/conversations/{$conversation->id}/messages", [
            'body' => 'Illicit message attempt by panel.',
        ], $this->authHeaders($panelUser))->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 19: Jury Panel can offer mediation
    // -------------------------------------------------------------------------
    #[Test]
    public function test_19_jury_panel_can_offer_mediation(): void
    {
        $comp = $this->createUser('comp19@test.com');
        $resp = $this->createUser('resp19@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Nineteen', 'jp19@panel.test');
        $this->assignPanelToCase($panel, $case);

        $res = $this->postJson("/api/jury/cases/{$case->id}/mediation/offer", [], $this->authHeaders($panelUser));
        $res->assertStatus(201);
        $res->assertJsonPath('data.status', 'offered');
        $this->assertStringContainsString($panel->panel_code, $res->json('data.initiator_name'));

        $this->assertDatabaseHas('tribunal_case_events', [
            'tribunal_case_id' => $case->id,
            'event_type' => 'jury_panel_mediation_offered',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 20: Jury Panel cannot accept mediation for a party
    // -------------------------------------------------------------------------
    #[Test]
    public function test_20_jury_panel_cannot_accept_mediation_for_party(): void
    {
        $comp = $this->createUser('comp20@test.com');
        $resp = $this->createUser('resp20@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Twenty', 'jp20@panel.test');
        $this->assignPanelToCase($panel, $case);

        $medRes = $this->postJson("/api/jury/cases/{$case->id}/mediation/offer", [], $this->authHeaders($panelUser));
        $mediationId = $medRes->json('data.id');

        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", [
            'response' => 'accepted',
        ], $this->authHeaders($panelUser))->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 21: Jury Panel cannot accept settlement proposal
    // -------------------------------------------------------------------------
    #[Test]
    public function test_21_jury_panel_cannot_accept_settlement_proposal(): void
    {
        $comp = $this->createUser('comp21@test.com');
        $resp = $this->createUser('resp21@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Twenty-One', 'jp21@panel.test');
        $this->assignPanelToCase($panel, $case);

        // Offer and accept mediation by both parties to make it active
        $medRes = $this->postJson("/api/jury/cases/{$case->id}/mediation/offer", [], $this->authHeaders($panelUser));
        $mediationId = $medRes->json('data.id');

        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($comp))->assertStatus(200);
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($resp))->assertStatus(200);

        // Complainant creates proposal
        $propRes = $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => 'Pay $25,000 to resolve all claims.',
        ], $this->authHeaders($comp))->assertStatus(201);

        $proposalId = $propRes->json('data.id');

        // Jury Panel attempts to accept proposal -> 403
        $this->postJson("/api/tribunal/settlement-proposals/{$proposalId}/accept", [], $this->authHeaders($panelUser))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TEST 22: Jury Panel can view mediation proposal history
    // -------------------------------------------------------------------------
    #[Test]
    public function test_22_jury_panel_can_view_mediation_proposal_history(): void
    {
        $comp = $this->createUser('comp22@test.com');
        $resp = $this->createUser('resp22@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Twenty-Two', 'jp22@panel.test');
        $this->assignPanelToCase($panel, $case);

        $medRes = $this->postJson("/api/jury/cases/{$case->id}/mediation/offer", [], $this->authHeaders($panelUser));
        $mediationId = $medRes->json('data.id');

        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($comp))->assertStatus(200);
        $this->postJson("/api/tribunal/mediations/{$mediationId}/respond", ['response' => 'accepted'], $this->authHeaders($resp))->assertStatus(200);

        $this->postJson("/api/tribunal/mediations/{$mediationId}/proposals", [
            'terms' => 'Initial settlement terms proposal.',
        ], $this->authHeaders($comp))->assertStatus(201);

        $viewRes = $this->getJson("/api/jury/cases/{$case->id}/mediation", $this->authHeaders($panelUser));
        $viewRes->assertStatus(200);
        $viewRes->assertJsonPath('data.status', 'active');
        $this->assertCount(1, $viewRes->json('data.proposals'));
        $this->assertEquals('Initial settlement terms proposal.', $viewRes->json('data.proposals.0.terms'));
    }

    // -------------------------------------------------------------------------
    // TEST 23: Jury Panel can end failed mediation where allowed
    // -------------------------------------------------------------------------
    #[Test]
    public function test_23_jury_panel_can_end_failed_mediation(): void
    {
        $comp = $this->createUser('comp23@test.com');
        $resp = $this->createUser('resp23@test.com');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Twenty-Three', 'jp23@panel.test');
        $this->assignPanelToCase($panel, $case);

        $medRes = $this->postJson("/api/jury/cases/{$case->id}/mediation/offer", [], $this->authHeaders($panelUser));
        $mediationId = $medRes->json('data.id');

        $endRes = $this->postJson("/api/jury/cases/{$case->id}/mediation/end", [
            'reason' => 'Parties unable to agree on procedural framework.',
        ], $this->authHeaders($panelUser));

        $endRes->assertStatus(200);
        $endRes->assertJsonPath('data.status', 'failed');
        $endRes->assertJsonPath('data.failure_reason', 'Parties unable to agree on procedural framework.');

        $this->assertDatabaseHas('tribunal_case_events', [
            'tribunal_case_id' => $case->id,
            'event_type' => 'jury_panel_mediation_failed',
        ]);
    }

    // -------------------------------------------------------------------------
    // TEST 24: Private lawyer-client messages never appear in shared room
    // -------------------------------------------------------------------------
    #[Test]
    public function test_24_private_lawyer_client_messages_never_appear_in_shared_room(): void
    {
        $comp = $this->createUser('comp24@test.com');
        $resp = $this->createUser('resp24@test.com');
        $lawyer = $this->createVerifiedLawyer('lawyer24@bar.test');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Twenty-Four', 'jp24@panel.test');
        $this->assignPanelToCase($panel, $case);

        $conversation = TribunalConversation::create([
            'tribunal_case_id' => $case->id,
            'client_user_id' => $comp->id,
            'representative_user_id' => $lawyer->id,
            'type' => TribunalConversationType::ComplainantRepresentative,
        ]);

        TribunalMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $lawyer->id,
            'body' => 'TOP SECRET LITIGATION STRATEGY - PRIVILEGED ATTORNEY CLIENT CHAT',
        ]);

        $roomRes = $this->getJson("/api/jury/cases/{$case->id}/case-room/messages", $this->authHeaders($panelUser));
        $roomRes->assertStatus(200);

        $allBodies = collect($roomRes->json('data'))->pluck('body')->all();
        $this->assertNotContains('TOP SECRET LITIGATION STRATEGY - PRIVILEGED ATTORNEY CLIENT CHAT', $allBodies);
    }

    // -------------------------------------------------------------------------
    // TEST 25: Old individual adjudicator does not gain authority for new Jury Panel case
    // -------------------------------------------------------------------------
    #[Test]
    public function test_25_old_adjudicator_does_not_gain_authority_for_new_jury_panel_case(): void
    {
        $comp = $this->createUser('comp25@test.com');
        $resp = $this->createUser('resp25@test.com');
        $oldAdjudicator = $this->createVerifiedLawyer('old_adj@bar.test');
        $case = $this->createCase($comp, $resp);
        [$panel, $panelUser] = $this->createJuryPanel('Panel Twenty-Five', 'jp25@panel.test');
        $this->assignPanelToCase($panel, $case);

        // Give old individual adjudicator an old accepted assignment record
        TribunalJuryAssignment::create([
            'tribunal_case_id' => $case->id,
            'juror_id' => $oldAdjudicator->id,
            'status' => 'accepted',
            'assigned_at' => now(),
            'responded_at' => now(),
        ]);

        // Case role for old adjudicator must NOT be 'adjudicator' because case has active Jury Panel!
        $this->assertNotEquals('adjudicator', $case->getUserCaseRole($oldAdjudicator->id));

        // Attempting to post procedural notice -> 403
        $this->postJson("/api/tribunal/cases/{$case->id}/case-room/procedural-notices", [
            'body' => 'Old adjudicator notice attempt.',
        ], $this->authHeaders($oldAdjudicator))->assertStatus(403);

        // Attempting to offer mediation -> 403
        $this->postJson("/api/tribunal/cases/{$case->id}/mediation/offer", [], $this->authHeaders($oldAdjudicator))
            ->assertStatus(403);
    }
}
