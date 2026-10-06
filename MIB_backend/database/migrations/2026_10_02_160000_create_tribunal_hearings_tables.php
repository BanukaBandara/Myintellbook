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
        // 1. Tribunal Hearings
        Schema::create('tribunal_hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')->constrained('tribunal_cases')->cascadeOnDelete();
            $table->foreignId('tribunal_jury_panel_id')->constrained('tribunal_jury_panels')->cascadeOnDelete();
            $table->string('hearing_number', 50)->unique();
            $table->string('hearing_type', 50)->default('formal');
            $table->string('status', 50)->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('location_type', 50)->default('online');
            $table->string('meeting_link')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('tribunal_case_id', 'th_case_id_idx');
            $table->index('status', 'th_status_idx');
            $table->index(['tribunal_case_id', 'status'], 'th_case_status_idx');
        });

        // 2. Tribunal Hearing Participants
        Schema::create('tribunal_hearing_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_hearing_id')->constrained('tribunal_hearings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('participant_type', 50);
            $table->string('side', 50)->nullable();
            $table->string('display_name');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attendance_status', 50)->default('invited');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index('tribunal_hearing_id', 'thp_hearing_id_idx');
            $table->index(['tribunal_hearing_id', 'user_id'], 'thp_hearing_user_idx');
        });

        // 3. Tribunal Witnesses
        Schema::create('tribunal_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')->constrained('tribunal_cases')->cascadeOnDelete();
            $table->foreignId('tribunal_hearing_id')->nullable()->constrained('tribunal_hearings')->nullOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->string('side', 50);
            $table->foreignId('witness_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('witness_name');
            $table->string('witness_email')->nullable();
            $table->text('relationship_to_case')->nullable();
            $table->text('statement_summary')->nullable();
            $table->string('status', 50)->default('proposed');
            $table->timestamp('approved_by_panel_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->index('tribunal_case_id', 'tw_case_id_idx');
            $table->index('status', 'tw_status_idx');
            $table->index(['tribunal_case_id', 'status'], 'tw_case_status_idx');
        });

        // 4. Tribunal Hearing Entries (Immutable hearing transcript record)
        Schema::create('tribunal_hearing_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_hearing_id')->constrained('tribunal_hearings')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('participant_type', 50);
            $table->string('side', 50)->nullable();
            $table->string('entry_type', 50);
            $table->text('body');
            $table->foreignId('related_witness_id')->nullable()->constrained('tribunal_witnesses')->nullOnDelete();
            $table->foreignId('related_evidence_id')->nullable()->constrained('tribunal_evidence')->nullOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('target_side', 50)->nullable();
            $table->foreignId('parent_entry_id')->nullable()->constrained('tribunal_hearing_entries')->nullOnDelete();
            $table->timestamps();

            $table->index('tribunal_hearing_id', 'the_hearing_id_idx');
            $table->index(['tribunal_hearing_id', 'sequence_number'], 'the_seq_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_hearing_entries');
        Schema::dropIfExists('tribunal_witnesses');
        Schema::dropIfExists('tribunal_hearing_participants');
        Schema::dropIfExists('tribunal_hearings');
    }
};
