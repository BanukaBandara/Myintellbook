<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Testament;
use App\Models\TestamentAuditLog;
use App\Models\TestamentResourceNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestamentTest extends TestCase
{
    use RefreshDatabase;

    private User $testator;
    private User $witness;
    private array $testatorHeaders;
    private array $witnessHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        [$this->testator, $this->testatorHeaders] = $this->member();
        [$this->witness, $this->witnessHeaders] = $this->member();
    }

    #[Test]
    public function full_lifecycle_from_draft_to_sealed_with_an_intact_audit_chain(): void
    {
        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', $this->completeDraft())
            ->assertOk()
            ->assertJsonPath('testament.status', 'draft')
            ->assertJsonPath('testament.title', 'My last will')
            ->assertJsonPath('testament.verification.encrypted_at_rest', true);

        // Content is ciphertext in the database.
        $raw = DB::table('testaments')->first();
        $this->assertStringNotContainsString('My last will', $raw->title);
        $this->assertStringNotContainsString('Alice', $raw->beneficiaries);

        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/submit', ['consent' => true])
            ->assertOk()
            ->assertJsonPath('testament.status', 'awaiting_witness')
            ->assertJsonPath('testament.witness.status', 'pending');

        // Locked while awaiting the witness.
        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', ['title' => 'Changed'])->assertStatus(409);

        // The witness sees the request but none of the content.
        $requests = $this->withHeaders($this->witnessHeaders)->getJson('/api/testament/witness-requests')
            ->assertOk()
            ->assertJsonCount(1, 'requests');
        $this->assertStringNotContainsString('My last will', $requests->getContent());
        $id = $requests->json('requests.0.id');

        $this->withHeaders($this->witnessHeaders)->postJson("/api/testament/witness-requests/{$id}/confirm", [])
            ->assertUnprocessable();
        $this->withHeaders($this->witnessHeaders)->postJson("/api/testament/witness-requests/{$id}/confirm", ['attestation' => true])
            ->assertOk();

        $show = $this->withHeaders($this->testatorHeaders)->getJson('/api/testament')
            ->assertOk()
            ->assertJsonPath('testament.status', 'sealed')
            ->assertJsonPath('testament.tribunal_status', 'awaiting_review')
            ->assertJsonPath('testament.verification.content_hash_matches', true)
            ->assertJsonPath('testament.verification.audit_chain_intact', true);

        $this->assertSame(
            ['draft_created', 'consent_given', 'witness_requested', 'witness_confirmed', 'sealed'],
            array_column($show->json('testament.audit'), 'event'),
        );
    }

    #[Test]
    public function submission_is_rejected_until_every_requirement_is_met(): void
    {
        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', [
            'title' => 'Draft',
            'beneficiaries' => [['name' => 'Alice', 'relationship' => 'Daughter', 'share' => 60]],
        ])->assertOk();

        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/submit', ['consent' => true])
            ->assertUnprocessable()
            ->assertJsonFragment(['Write your instructions.'])
            ->assertJsonFragment(['Complete the health declaration.'])
            ->assertJsonFragment(['Beneficiary shares must add up to 100%.'])
            ->assertJsonFragment(['Choose a witness.']);

        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/submit', [])->assertUnprocessable();
    }

    #[Test]
    public function you_cannot_be_your_own_witness(): void
    {
        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', ['witness_user_id' => $this->testator->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('witness_user_id');
    }

    #[Test]
    public function a_declined_request_returns_the_testament_to_draft(): void
    {
        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', $this->completeDraft())->assertOk();
        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/submit', ['consent' => true])->assertOk();
        $id = Testament::query()->value('id');

        $this->withHeaders($this->witnessHeaders)->postJson("/api/testament/witness-requests/{$id}/decline")->assertOk();

        $this->withHeaders($this->testatorHeaders)->getJson('/api/testament')
            ->assertJsonPath('testament.status', 'draft')
            ->assertJsonPath('testament.witness.status', 'declined');
    }

    #[Test]
    public function only_the_chosen_witness_can_respond_and_others_cannot_read_the_testament(): void
    {
        [, $strangerHeaders] = $this->member();
        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', $this->completeDraft())->assertOk();
        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/submit', ['consent' => true])->assertOk();
        $id = Testament::query()->value('id');

        $this->withHeaders($strangerHeaders)->postJson("/api/testament/witness-requests/{$id}/confirm", ['attestation' => true])
            ->assertNotFound();
        $this->withHeaders($strangerHeaders)->getJson('/api/testament')->assertJsonPath('testament', null);
    }

    #[Test]
    public function tampering_with_the_audit_log_is_detected_and_entries_are_immutable(): void
    {
        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', $this->completeDraft())->assertOk();
        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/submit', ['consent' => true])->assertOk();

        $entry = TestamentAuditLog::query()->first();
        $this->expectException(LogicException::class);
        try {
            $entry->update(['event' => 'forged']);
        } finally {
            // A direct database edit bypasses the model guard but breaks the hash chain.
            DB::table('testament_audit_logs')->where('id', $entry->id)->update(['event' => 'forged']);
            $this->assertFalse(TestamentAuditLog::verifyChain($entry->testament_id));
        }
    }

    #[Test]
    public function withdrawing_allows_a_fresh_draft(): void
    {
        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', $this->completeDraft())->assertOk();
        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/withdraw', ['confirm' => true])->assertOk();
        $this->withHeaders($this->testatorHeaders)->getJson('/api/testament')->assertJsonPath('testament', null);

        $this->withHeaders($this->testatorHeaders)->putJson('/api/testament', ['title' => 'New draft'])
            ->assertOk()
            ->assertJsonPath('testament.title', 'New draft');
        $this->assertDatabaseCount('testaments', 2);
    }

    #[Test]
    public function members_can_publish_and_read_active_community_resource_notes(): void
    {
        $payload = [
            'title' => 'Workshop tools',
            'description' => 'A set of tools available to a community member who can use them.',
            'category' => 'Equipment',
            'phone' => '+94112223344',
            'email' => 'owner@example.com',
            'location' => 'Colombo',
        ];

        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/notes', $payload)
            ->assertCreated()
            ->assertJsonPath('note.title', 'Workshop tools')
            ->assertJsonPath('note.status', 'active')
            ->assertJsonPath('note.email', 'owner@example.com')
            ->assertJsonPath('note.owner_name', 'A member');

        $this->assertDatabaseHas('testament_resource_notes', [
            'user_id' => $this->testator->id,
            'title' => 'Workshop tools',
            'status' => 'active',
        ]);

        $this->withHeaders($this->witnessHeaders)->getJson('/api/testament/notes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.description', $payload['description'])
            ->assertJsonPath('notes.0.phone', $payload['phone'])
            ->assertJsonPath('notes.0.email', $payload['email'])
            ->assertJsonPath('notes.0.location', 'Colombo');

        $this->withHeaders($this->witnessHeaders)->postJson('/api/testament/notes', [
            'title' => 'Community learning notes',
            'description' => 'Notes from a community member.',
            'category' => 'Learning',
        ])->assertCreated();

        TestamentResourceNote::query()->create([
            'user_id' => $this->witness->id,
            'title' => 'Private inactive note',
            'description' => 'This should not appear.',
            'category' => 'Other',
            'status' => 'inactive',
        ]);

        $this->withHeaders($this->testatorHeaders)->getJson('/api/testament/notes')
            ->assertJsonCount(2, 'notes')
            ->assertJsonMissing(['title' => 'Private inactive note']);
    }

    #[Test]
    public function resource_note_contact_details_are_optional_but_validated_when_supplied(): void
    {
        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/notes', [
            'title' => 'Shared reference notes',
            'description' => 'A useful set of notes.',
            'category' => 'Learning',
        ])->assertCreated()
            ->assertJsonPath('note.phone', null)
            ->assertJsonPath('note.email', null);

        $this->withHeaders($this->testatorHeaders)->postJson('/api/testament/notes', [
            'title' => 'Invalid contact',
            'description' => 'An invalid email must not be stored.',
            'category' => 'Learning',
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    private function completeDraft(): array
    {
        return [
            'title' => 'My last will',
            'instructions' => 'Distribute my estate as listed.',
            'health' => ['sound_mind' => true, 'no_duress' => true, 'physician_name' => 'Dr. Perera', 'assessment_date' => now()->subDay()->toDateString()],
            'beneficiaries' => [
                ['name' => 'Alice', 'relationship' => 'Daughter', 'share' => 60],
                ['name' => 'Bob', 'relationship' => 'Son', 'share' => 40],
            ],
            'witness_user_id' => $this->witness->id,
        ];
    }

    private function member(): array
    {
        $user = User::create(['email' => Str::lower(Str::random(10)).'@example.com', 'password' => 'password123']);
        $raw = Str::random(40);
        ApiToken::create(['user_id' => $user->id, 'token' => hash('sha256', $raw), 'expires_at' => now()->addDay()]);

        return [$user, ['Authorization' => "Bearer {$raw}", 'Accept' => 'application/json']];
    }
}
