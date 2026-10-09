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
        Schema::create('internal_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number', 32)->nullable()->unique();
            $table->foreignId('reporter_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('reported_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('category', 100);
            $table->string('subject', 200);
            $table->text('description');
            $table->string('status', 40)->default('Submitted');
            $table->string('severity', 20)->default('medium');
            $table->text('admin_notes')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('category');
            $table->index('reporter_user_id');
            $table->index('reported_user_id');
        });

        Schema::create('internal_report_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_report_id')
                ->constrained('internal_reports')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->boolean('is_confidential')->default(true);
            $table->timestamps();

            $table->index('internal_report_id');
        });

        Schema::create('internal_report_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_report_id')
                ->constrained('internal_reports')
                ->cascadeOnDelete();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('internal_report_id');
        });

        Schema::create('internal_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_report_id')
                ->constrained('internal_reports')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action_type', 60);
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->foreignId('applied_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('applied_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index('internal_report_id');
            $table->index('user_id');
            $table->index('action_type');
        });

        Schema::create('internal_report_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_report_id')
                ->constrained('internal_reports')
                ->cascadeOnDelete();
            $table->string('action', 100);
            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('internal_report_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_report_audits');
        Schema::dropIfExists('internal_penalties');
        Schema::dropIfExists('internal_report_reviews');
        Schema::dropIfExists('internal_report_evidence');
        Schema::dropIfExists('internal_reports');
    }
};
