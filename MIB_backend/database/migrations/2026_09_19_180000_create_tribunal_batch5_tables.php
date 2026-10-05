<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Shared Tribunal Case Rooms
        if (!Schema::hasTable('tribunal_case_rooms')) {
            Schema::create('tribunal_case_rooms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tribunal_case_id')
                    ->unique()
                    ->constrained('tribunal_cases', indexName: 'tcr_case_fk')
                    ->cascadeOnDelete();
                $table->string('status', 30)->default('active'); // active, closed
                $table->dateTime('opened_at');
                $table->dateTime('closed_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        // 2. Shared Tribunal Case Messages
        if (!Schema::hasTable('tribunal_case_messages')) {
            Schema::create('tribunal_case_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tribunal_case_room_id')
                    ->constrained('tribunal_case_rooms', indexName: 'tcm_room_fk')
                    ->cascadeOnDelete();
                $table->foreignId('tribunal_case_id')
                    ->constrained('tribunal_cases', indexName: 'tcm_case_fk')
                    ->cascadeOnDelete();
                $table->foreignId('sender_id')
                    ->constrained('users', indexName: 'tcm_sender_fk')
                    ->cascadeOnDelete();
                $table->string('sender_case_role', 50)->nullable(); // complainant, respondent, complainant_representative, respondent_representative, adjudicator, system
                $table->string('message_type', 50)->default('message'); // message, procedural_notice, adjudicator_question, question_response, mediation_notice, system_notice
                $table->text('body');
                $table->string('target_side', 30)->nullable(); // complainant, respondent, both
                $table->unsignedBigInteger('parent_message_id')->nullable();
                $table->foreign('parent_message_id', 'tcm_parent_fk')
                    ->references('id')
                    ->on('tribunal_case_messages')
                    ->nullOnDelete();
                $table->unsignedBigInteger('related_evidence_id')->nullable();
                $table->foreign('related_evidence_id', 'tcm_evid_fk')
                    ->references('id')
                    ->on('tribunal_evidence')
                    ->nullOnDelete();
                $table->boolean('procedural')->default(false);
                $table->timestamps();

                $table->index('tribunal_case_room_id');
                $table->index('tribunal_case_id');
                $table->index('sender_id');
                $table->index('message_type');
                $table->index('parent_message_id');
                $table->index('created_at');
            });
        }

        // 3. Tribunal Mediations
        if (!Schema::hasTable('tribunal_mediations')) {
            Schema::create('tribunal_mediations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tribunal_case_id')
                    ->constrained('tribunal_cases', indexName: 'tmed_case_fk')
                    ->cascadeOnDelete();
                $table->foreignId('initiated_by')
                    ->constrained('users', indexName: 'tmed_init_fk')
                    ->cascadeOnDelete();
                $table->string('initiation_type', 30); // party_request, adjudicator_offer
                $table->string('status', 30)->default('offered'); // offered, awaiting_consent, active, settled, declined, failed, cancelled
                $table->string('previous_case_status', 50);
                $table->dateTime('offered_at');
                $table->dateTime('started_at')->nullable();
                $table->dateTime('ended_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();

                $table->index('tribunal_case_id');
                $table->index('status');
            });
        }

        // 4. Tribunal Mediation Consents
        if (!Schema::hasTable('tribunal_mediation_consents')) {
            Schema::create('tribunal_mediation_consents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tribunal_mediation_id')
                    ->constrained('tribunal_mediations', indexName: 'tmc_med_fk')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users', indexName: 'tmc_user_fk')
                    ->cascadeOnDelete();
                $table->string('side', 30); // complainant, respondent
                $table->string('response', 30)->default('pending'); // pending, accepted, declined
                $table->dateTime('responded_at')->nullable();
                $table->timestamps();

                $table->unique(['tribunal_mediation_id', 'user_id'], 'tmc_med_user_uniq');
                $table->index('tribunal_mediation_id');
            });
        }

        // 5. Tribunal Settlement Proposals
        if (!Schema::hasTable('tribunal_settlement_proposals')) {
            Schema::create('tribunal_settlement_proposals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tribunal_mediation_id')
                    ->constrained('tribunal_mediations', indexName: 'tsp_med_fk')
                    ->cascadeOnDelete();
                $table->foreignId('proposed_by')
                    ->constrained('users', indexName: 'tsp_prop_fk')
                    ->cascadeOnDelete();
                $table->string('proposed_by_side', 30); // complainant, respondent
                $table->unsignedBigInteger('parent_proposal_id')->nullable();
                $table->foreign('parent_proposal_id', 'tsp_parent_fk')
                    ->references('id')
                    ->on('tribunal_settlement_proposals')
                    ->nullOnDelete();
                $table->unsignedInteger('version_number')->default(1);
                $table->text('terms');
                $table->string('status', 30)->default('pending'); // pending, accepted, rejected, countered, withdrawn, superseded
                $table->timestamps();

                $table->index('tribunal_mediation_id');
                $table->index('status');
            });
        }

        // 6. Tribunal Settlement Acceptances
        if (!Schema::hasTable('tribunal_settlement_acceptances')) {
            Schema::create('tribunal_settlement_acceptances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('settlement_proposal_id')
                    ->constrained('tribunal_settlement_proposals', indexName: 'tsa_prop_fk')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users', indexName: 'tsa_user_fk')
                    ->cascadeOnDelete();
                $table->string('side', 30); // complainant, respondent
                $table->dateTime('accepted_at');
                $table->timestamps();

                $table->unique(['settlement_proposal_id', 'user_id'], 'tsa_prop_user_uniq');
            });
        }

        // 7. Tribunal Settlement Agreements (Immutable Final Agreement)
        if (!Schema::hasTable('tribunal_settlement_agreements')) {
            Schema::create('tribunal_settlement_agreements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tribunal_case_id')
                    ->constrained('tribunal_cases', indexName: 'tsag_case_fk')
                    ->cascadeOnDelete();
                $table->foreignId('tribunal_mediation_id')
                    ->constrained('tribunal_mediations', indexName: 'tsag_med_fk')
                    ->cascadeOnDelete();
                $table->foreignId('settlement_proposal_id')
                    ->constrained('tribunal_settlement_proposals', indexName: 'tsag_prop_fk')
                    ->cascadeOnDelete();
                $table->string('agreement_number', 50)->unique();
                $table->text('terms_snapshot');
                $table->dateTime('complainant_accepted_at');
                $table->dateTime('respondent_accepted_at');
                $table->dateTime('finalized_at');
                $table->timestamps();

                $table->index('tribunal_case_id');
                $table->index('tribunal_mediation_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_settlement_agreements');
        Schema::dropIfExists('tribunal_settlement_acceptances');
        Schema::dropIfExists('tribunal_settlement_proposals');
        Schema::dropIfExists('tribunal_mediation_consents');
        Schema::dropIfExists('tribunal_mediations');
        Schema::dropIfExists('tribunal_case_messages');
        Schema::dropIfExists('tribunal_case_rooms');
    }
};
