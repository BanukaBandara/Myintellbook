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
        // 1. Case Audit Events Log
        Schema::create('tribunal_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')
                ->constrained('tribunal_cases')
                ->cascadeOnDelete();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('event_type', 60);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('tribunal_case_id');
            $table->index('event_type');
        });

        // 2. Evidence
        Schema::create('tribunal_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')
                ->constrained('tribunal_cases')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('evidence_number', 20);
            $table->string('type', 30);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('stored_filename', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256_hash', 64)->nullable();
            $table->text('external_url')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['tribunal_case_id', 'evidence_number']);
            $table->index('tribunal_case_id');
            $table->index('uploaded_by');
            $table->index('status');
        });

        // 3. Evidence Challenges
        Schema::create('tribunal_evidence_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_evidence_id')
                ->constrained('tribunal_evidence')
                ->cascadeOnDelete();
            $table->foreignId('challenged_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('tribunal_evidence_id');
            $table->index('challenged_by');
            $table->index('status');
        });

        // 4. Juror Profiles (Eligibility)
        Schema::create('tribunal_juror_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('status', 30)->default('eligible');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('training_completed_at')->nullable();
            $table->boolean('available')->default(true);
            $table->integer('cases_active')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('available');
        });

        // 5. Jury Assignments
        Schema::create('tribunal_jury_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')
                ->constrained('tribunal_cases')
                ->cascadeOnDelete();
            $table->foreignId('juror_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('role', 30)->default('juror');
            $table->string('status', 30)->default('invited');
            $table->timestamp('assigned_at');
            $table->timestamp('responded_at')->nullable();
            $table->text('recusal_reason')->nullable();
            $table->timestamps();

            $table->index('tribunal_case_id');
            $table->index('juror_id');
            $table->index('status');
        });

        // 6. Juror Conflict-of-Interest Declarations
        Schema::create('tribunal_juror_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_jury_assignment_id')
                ->constrained('tribunal_jury_assignments')
                ->cascadeOnDelete();
            $table->foreignId('juror_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('has_conflict');
            $table->text('conflict_reason')->nullable();
            $table->timestamp('declared_at');
            $table->timestamps();

            $table->index('tribunal_jury_assignment_id');
            $table->index('juror_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_juror_conflicts');
        Schema::dropIfExists('tribunal_jury_assignments');
        Schema::dropIfExists('tribunal_juror_profiles');
        Schema::dropIfExists('tribunal_evidence_challenges');
        Schema::dropIfExists('tribunal_evidence');
        Schema::dropIfExists('tribunal_case_events');
    }
};
