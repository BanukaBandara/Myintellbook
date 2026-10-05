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
        // 1. Add is_admin column to users table if not present
        if (!Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin')->default(false)->after('email');
            });
        }

        // 2. Professional Verifications Table
        if (!Schema::hasTable('professional_verifications')) {
            Schema::create('professional_verifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->string('profession_type', 50);
                $table->string('verification_status', 30)->default('pending');
                $table->string('registration_number', 100)->nullable();
                $table->string('enrollment_number', 100)->nullable();
                $table->string('issuing_authority', 255)->nullable();
                $table->unsignedInteger('years_of_experience')->nullable();
                $table->string('qualification_document_path', 500)->nullable();
                $table->string('identity_document_path', 500)->nullable();
                $table->string('additional_document_path', 500)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->text('suspension_reason')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('verification_status');
                $table->index('profession_type');
            });
        }

        // 3. Professional Verification Audit Log
        if (!Schema::hasTable('professional_verification_events')) {
            Schema::create('professional_verification_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('professional_verification_id');
                $table->foreign('professional_verification_id', 'pve_verification_fk')
                    ->references('id')
                    ->on('professional_verifications')
                    ->cascadeOnDelete();

                $table->foreignId('actor_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('event_type', 60);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('professional_verification_id', 'pve_verification_idx');
                $table->index('event_type');
            });
        }

        // 4. Tribunal Adjudicator Profiles Table
        if (!Schema::hasTable('tribunal_adjudicator_profiles')) {
            Schema::create('tribunal_adjudicator_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->unique()
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('professional_verification_id')->nullable();
                $table->foreign('professional_verification_id', 'tap_verification_fk')
                    ->references('id')
                    ->on('professional_verifications')
                    ->nullOnDelete();

                $table->string('status', 30)->default('pending');
                $table->string('qualification_status', 30)->default('not_started');

                $table->unsignedBigInteger('qualification_exam_id')->nullable();
                $table->foreign('qualification_exam_id', 'tap_exam_fk')
                    ->references('id')
                    ->on('exams')
                    ->nullOnDelete();

                $table->decimal('qualification_score', 5, 2)->nullable();
                $table->timestamp('qualified_at')->nullable();
                $table->timestamp('training_completed_at')->nullable();
                $table->boolean('available')->default(false);
                $table->unsignedInteger('max_active_cases')->default(5);
                $table->unsignedInteger('cases_active')->default(0);
                $table->string('experience_level', 50)->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->text('suspension_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('qualification_status');
                $table->index('available');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_adjudicator_profiles');
        Schema::dropIfExists('professional_verification_events');
        Schema::dropIfExists('professional_verifications');

        if (Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_admin');
            });
        }
    }
};
