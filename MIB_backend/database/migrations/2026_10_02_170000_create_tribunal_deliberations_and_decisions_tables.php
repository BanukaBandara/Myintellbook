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
        // 1. Tribunal Deliberations
        Schema::create('tribunal_deliberations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')->constrained('tribunal_cases')->cascadeOnDelete();
            $table->foreignId('tribunal_jury_panel_id')->constrained('tribunal_jury_panels')->cascadeOnDelete();
            $table->string('status', 50)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('tribunal_case_id', 'td_case_id_idx');
            $table->index(['tribunal_case_id', 'status'], 'td_case_status_idx');
        });

        // 2. Private Deliberation Notes (Strictly Panel-Private)
        Schema::create('tribunal_deliberation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_deliberation_id')->constrained('tribunal_deliberations')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('note_type', 50)->default('general');
            $table->text('body');
            $table->timestamps();

            $table->index('tribunal_deliberation_id', 'tdn_delib_id_idx');
            $table->index(['tribunal_deliberation_id', 'note_type'], 'tdn_delib_type_idx');
        });

        // 3. Findings of Fact & Issues
        Schema::create('tribunal_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')->constrained('tribunal_cases')->cascadeOnDelete();
            $table->foreignId('tribunal_deliberation_id')->constrained('tribunal_deliberations')->cascadeOnDelete();
            $table->string('finding_number', 50);
            $table->string('finding_type', 50)->default('fact');
            $table->string('title')->nullable();
            $table->text('finding_text');
            $table->string('conclusion', 50)->default('established');
            $table->unsignedInteger('display_order')->default(1);
            $table->boolean('is_public')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('tribunal_case_id', 'tf_case_id_idx');
            $table->index('tribunal_deliberation_id', 'tf_delib_id_idx');
            $table->index(['tribunal_case_id', 'display_order'], 'tf_case_order_idx');
        });

        // 4. Finding Evidence Pivot
        Schema::create('tribunal_finding_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_finding_id')->constrained('tribunal_findings', 'id', 'tfe_find_fk')->cascadeOnDelete();
            $table->foreignId('tribunal_evidence_id')->constrained('tribunal_evidence', 'id', 'tfe_evid_fk')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tribunal_finding_id', 'tribunal_evidence_id'], 'tfe_find_evid_uniq');
        });

        // 5. Finding Hearing Entries Pivot
        Schema::create('tribunal_finding_hearing_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_finding_id')->constrained('tribunal_findings', 'id', 'tfhe_find_fk')->cascadeOnDelete();
            $table->foreignId('tribunal_hearing_entry_id')->constrained('tribunal_hearing_entries', 'id', 'tfhe_entry_fk')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tribunal_finding_id', 'tribunal_hearing_entry_id'], 'tfhe_find_entry_uniq');
        });

        // 6. Finding Witnesses Pivot
        Schema::create('tribunal_finding_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_finding_id')->constrained('tribunal_findings', 'id', 'tfw_find_fk')->cascadeOnDelete();
            $table->foreignId('tribunal_witness_id')->constrained('tribunal_witnesses', 'id', 'tfw_witn_fk')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tribunal_finding_id', 'tribunal_witness_id'], 'tfw_find_witn_uniq');
        });

        // 7. Final Tribunal Decisions
        Schema::create('tribunal_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')->constrained('tribunal_cases')->cascadeOnDelete();
            $table->foreignId('tribunal_jury_panel_id')->constrained('tribunal_jury_panels')->cascadeOnDelete();
            $table->string('decision_number', 50)->unique();
            $table->string('status', 50)->default('draft');
            $table->string('outcome', 50)->nullable();
            $table->text('summary')->nullable();
            $table->text('reasoning')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('appeal_deadline')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('tribunal_case_id', 'tdec_case_id_idx');
            $table->index(['tribunal_case_id', 'status'], 'tdec_case_status_idx');
        });

        // 8. Tribunal Decision Orders / Remedies
        Schema::create('tribunal_decision_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_decision_id')->constrained('tribunal_decisions')->cascadeOnDelete();
            $table->string('order_number', 50);
            $table->string('order_type', 50)->default('warning');
            $table->string('title');
            $table->text('description');
            $table->string('target_side', 50)->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->string('status', 50)->default('recorded');
            $table->timestamps();

            $table->index('tribunal_decision_id', 'tdo_decision_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_decision_orders');
        Schema::dropIfExists('tribunal_decisions');
        Schema::dropIfExists('tribunal_finding_witnesses');
        Schema::dropIfExists('tribunal_finding_hearing_entries');
        Schema::dropIfExists('tribunal_finding_evidence');
        Schema::dropIfExists('tribunal_findings');
        Schema::dropIfExists('tribunal_deliberation_notes');
        Schema::dropIfExists('tribunal_deliberations');
    }
};
