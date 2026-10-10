<?php

namespace Tests\Feature;

use App\Enums\ProfessionalType;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\TribunalJuryPanelStatus;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\ProfessionalVerification;
use App\Models\Profile;
use App\Models\TribunalCase;
use App\Models\TribunalJuryPanel;
use App\Models\User;
use App\Models\profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TribunalRespondentSearchTest extends TestCase
{
    use RefreshDatabase;

    protected int $professionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);

        $cat = Category::create(['name' => 'Legal Category']);
        $prof = profession::create([
            'category_id' => $cat->id,
            'name' => 'Attorney',
        ]);
        $this->professionId = $prof->id;
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

    protected function createUserWithProfile(
        string $email,
        string $firstName,
        string $lastName,
        bool $isAdmin = false,
        ?string $profileImage = null
    ): User {
        $user = User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'is_admin' => $isAdmin,
        ]);

        Profile::create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 1,
            'birth_date' => '1995-05-15',
            'profession_id' => $this->professionId,
            'profile_image' => $profileImage,
            'slug' => Str::slug("{$firstName} {$lastName}"),
            'uuid' => (string) Str::uuid(),
        ]);

        return $user;
    }

    #[Test]
    public function test_01_authenticated_user_can_search_eligible_users(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $respondent = $this->createUserWithProfile('respondent@test.com', 'John', 'Perera');

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=John');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals($respondent->id, $data[0]['id']);
        $this->assertEquals('John Perera', $data[0]['name']);
    }

    #[Test]
    public function test_02_search_requires_authentication(): void
    {
        $response = $this->getJson('/api/tribunal/respondents/search?q=John');
        $response->assertStatus(401);
    }

    #[Test]
    public function test_03_self_is_excluded_from_search_results(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $otherAlice = $this->createUserWithProfile('alice.smith@test.com', 'Alice', 'Smith');

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Alice');

        $response->assertStatus(200);
        $data = $response->json('data');

        $ids = collect($data)->pluck('id')->all();
        $this->assertNotContains($complainant->id, $ids);
        $this->assertContains($otherAlice->id, $ids);
    }

    #[Test]
    public function test_04_jury_panel_accounts_are_excluded_from_search_results(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $panelUser = $this->createUserWithProfile('panel.user@test.com', 'Panel', 'Adjudicator');

        // Designate user as dedicated Jury Panel account
        TribunalJuryPanel::create([
            'panel_code' => 'PNL-TEST-01',
            'panel_name' => 'Test Panel',
            'login_user_id' => $panelUser->id,
            'status' => TribunalJuryPanelStatus::Active,
            'created_by' => $complainant->id,
        ]);

        $this->assertTrue($panelUser->isJuryPanelAccount());

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Panel');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($panelUser->id, $ids);
    }

    #[Test]
    public function test_05_super_admin_accounts_are_excluded_from_search_results(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $adminUser = $this->createUserWithProfile('admin@test.com', 'Super', 'Administrator', isAdmin: true);

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Admin');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($adminUser->id, $ids);
    }

    #[Test]
    public function test_06_normal_users_are_included_in_search_results(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $normal1 = $this->createUserWithProfile('user1@test.com', 'Robert', 'Silva');
        $normal2 = $this->createUserWithProfile('user2@test.com', 'Roberta', 'Fernando');

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Robert');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($normal1->id, $ids);
        $this->assertContains($normal2->id, $ids);
    }

    #[Test]
    public function test_07_verified_lawyer_can_appear_as_ordinary_respondent(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $lawyerUser = $this->createUserWithProfile('lawyer@test.com', 'David', 'Attorney');

        ProfessionalVerification::create([
            'user_id' => $lawyerUser->id,
            'profession_type' => ProfessionalType::AttorneyAtLaw,
            'registration_number' => 'REG-LAW-001',
            'enrollment_number' => 'ENR-LAW-001',
            'issuing_authority' => 'Supreme Court Bar',
            'verification_status' => ProfessionalVerificationStatus::Verified,
            'verified_at' => now(),
        ]);

        $this->assertTrue($lawyerUser->canActAsLegalRepresentative());

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=David');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals($lawyerUser->id, $data[0]['id']);
        $this->assertTrue($data[0]['is_verified_lawyer']);
        $this->assertEquals('Verified Attorney-at-Law', $data[0]['public_subtitle']);
    }

    #[Test]
    public function test_08_unmatched_accounts_excluded(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $this->createUserWithProfile('unmatched@test.com', 'Zachary', 'Taylor');

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Samantha');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    #[Test]
    public function test_09_search_matches_first_and_last_name(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $respondent = $this->createUserWithProfile('person@test.com', 'Kasun', 'Bandara');

        // Search by first name
        $res1 = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Kasun');
        $this->assertEquals($respondent->id, $res1->json('data.0.id'));

        // Search by last name
        $res2 = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Bandara');
        $this->assertEquals($respondent->id, $res2->json('data.0.id'));

        // Search by full concatenated name
        $res3 = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Kasun Bandara');
        $this->assertEquals($respondent->id, $res3->json('data.0.id'));
    }

    #[Test]
    public function test_10_search_matches_username_or_slug(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $respondent = $this->createUserWithProfile('techie@test.com', 'Nimal', 'Perera');

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=nimal-perera');

        $response->assertStatus(200);
        $this->assertEquals($respondent->id, $response->json('data.0.id'));
    }

    #[Test]
    public function test_11_empty_query_does_not_enumerate_all_users(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $this->createUserWithProfile('user1@test.com', 'User', 'One');
        $this->createUserWithProfile('user2@test.com', 'User', 'Two');

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    #[Test]
    public function test_12_query_shorter_than_minimum_rejected_or_empty(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $this->createUserWithProfile('john@test.com', 'John', 'Doe');

        // Query with only 2 chars
        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Jo');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    #[Test]
    public function test_13_result_fields_contain_only_safe_data_and_no_private_leaks(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $respondent = $this->createUserWithProfile('respondent@test.com', 'Michael', 'Scott');

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Michael');

        $response->assertStatus(200);
        $item = $response->json('data.0');

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('username', $item);
        $this->assertArrayHasKey('profile_photo_url', $item);
        $this->assertArrayHasKey('public_subtitle', $item);
        $this->assertArrayHasKey('profile_url', $item);
        $this->assertArrayHasKey('is_verified_lawyer', $item);

        // Strictly verify sensitive data is NOT present
        $this->assertArrayNotHasKey('email', $item);
        $this->assertArrayNotHasKey('password', $item);
        $this->assertArrayNotHasKey('remember_token', $item);
        $this->assertArrayNotHasKey('google_id', $item);
        $this->assertArrayNotHasKey('email_verification_token', $item);
        $this->assertArrayNotHasKey('is_admin', $item);
        $this->assertArrayNotHasKey('phone', $item);
        $this->assertArrayNotHasKey('address', $item);
    }

    #[Test]
    public function test_14_profile_picture_url_returned_safely(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $photoDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $respondent = $this->createUserWithProfile('hasphoto@test.com', 'Photo', 'User', profileImage: $photoDataUri);

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=Photo');

        $response->assertStatus(200);
        $item = $response->json('data.0');
        $this->assertEquals($photoDataUri, $item['profile_photo_url']);
    }

    #[Test]
    public function test_15_default_avatar_behavior_works_when_profile_photo_is_null(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $respondent = $this->createUserWithProfile('nophoto@test.com', 'NoPhoto', 'User', profileImage: null);

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=NoPhoto');

        $response->assertStatus(200);
        $item = $response->json('data.0');
        $this->assertNull($item['profile_photo_url']);
    }

    #[Test]
    public function test_16_result_limit_is_enforced(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');

        // Create 15 matching users
        for ($i = 1; $i <= 15; $i++) {
            $this->createUserWithProfile("sample{$i}@test.com", "TestPerson{$i}", "CommonSurname");
        }

        $response = $this->withHeaders($this->authHeaders($complainant))->getJson('/api/tribunal/respondents/search?q=CommonSurname');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(10, $data);
    }

    #[Test]
    public function test_17_user_can_create_case_using_selected_respondent(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $respondent = $this->createUserWithProfile('respondent@test.com', 'Bob', 'Respondent');

        $response = $this->withHeaders($this->authHeaders($complainant))->postJson('/api/tribunal/cases', [
                'respondent_id' => $respondent->id,
                'title' => 'Breach of Agreement Regarding Course Materials',
                'category' => 'Professional Dispute',
                'description' => 'The respondent distributed proprietary instructional materials without prior license or contractual consent.',
                'requested_resolution' => 'Immediate removal of materials and formal retraction.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('tribunal_cases', [
            'title' => 'Breach of Agreement Regarding Course Materials',
            'created_by' => $complainant->id,
        ]);
    }

    #[Test]
    public function test_18_backend_rejects_self_respondent_id(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');

        $response = $this->withHeaders($this->authHeaders($complainant))->postJson('/api/tribunal/cases', [
                'respondent_id' => $complainant->id,
                'title' => 'Attempted Self Dispute Claim',
                'category' => 'Other',
                'description' => 'Attempting to file a tribunal complaint where complainant is also respondent.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['respondent_id']);
    }

    #[Test]
    public function test_19_backend_rejects_jury_panel_respondent_id(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $panelUser = $this->createUserWithProfile('panel@test.com', 'Jury', 'PanelAccount');

        TribunalJuryPanel::create([
            'panel_code' => 'PNL-PANEL-01',
            'panel_name' => 'Adjudication Panel',
            'login_user_id' => $panelUser->id,
            'status' => TribunalJuryPanelStatus::Active,
            'created_by' => $complainant->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($complainant))->postJson('/api/tribunal/cases', [
                'respondent_id' => $panelUser->id,
                'title' => 'Attempted Claim Against Jury Panel',
                'category' => 'Other',
                'description' => 'Attempting to file a tribunal complaint where respondent is a jury panel account.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['respondent_id']);
    }

    #[Test]
    public function test_20_backend_rejects_super_admin_respondent_id(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $adminUser = $this->createUserWithProfile('admin@test.com', 'Super', 'Admin', isAdmin: true);

        $response = $this->withHeaders($this->authHeaders($complainant))->postJson('/api/tribunal/cases', [
                'respondent_id' => $adminUser->id,
                'title' => 'Attempted Claim Against Administrator',
                'category' => 'Other',
                'description' => 'Attempting to file a tribunal complaint where respondent is an admin account.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['respondent_id']);
    }

    #[Test]
    public function test_21_manually_forged_invalid_user_id_rejected(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');

        $response = $this->withHeaders($this->authHeaders($complainant))->postJson('/api/tribunal/cases', [
                'respondent_id' => 999999, // Non-existent user ID
                'title' => 'Forged Nonexistent User Claim',
                'category' => 'Other',
                'description' => 'Attempting to file a tribunal complaint against a forged non-existent user ID.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['respondent_id']);
    }

    #[Test]
    public function test_22_existing_case_creation_behavior_still_works(): void
    {
        $complainant = $this->createUserWithProfile('complainant@test.com', 'Alice', 'Complainant');
        $respondent = $this->createUserWithProfile('respondent@test.com', 'Bob', 'Respondent');

        $response = $this->withHeaders($this->authHeaders($complainant))->postJson('/api/tribunal/cases', [
                'respondent_id' => $respondent->id,
                'title' => 'Valid Complete Dispute Filing',
                'category' => 'Defamation',
                'description' => 'The respondent published unverified defamatory claims on community discussion threads.',
                'requested_resolution' => 'Full public apology and deletion of disputed posts.',
            ]);

        $response->assertStatus(201);
        $caseData = $response->json('data');

        $this->assertNotEmpty($caseData['case_number']);
        $this->assertEquals('jury_selection', $caseData['status']);
        $this->assertCount(2, $caseData['parties']);
    }
}
