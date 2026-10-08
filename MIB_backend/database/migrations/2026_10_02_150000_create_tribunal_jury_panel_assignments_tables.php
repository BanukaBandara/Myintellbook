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
        Schema::create('tribunal_jury_panel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')->constrained('tribunal_cases')->cascadeOnDelete();
            $table->foreignId('tribunal_jury_panel_id')->constrained('tribunal_jury_panels')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_method', 50)->default('automatic');
            $table->string('status', 50)->default('active');
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();

            $table->index('tribunal_case_id', 'tjpa_case_id_idx');
            $table->index('tribunal_jury_panel_id', 'tjpa_panel_id_idx');
            $table->index('status', 'tjpa_status_idx');
            $table->index(['tribunal_case_id', 'status'], 'tjpa_case_status_idx');
            $table->index(['tribunal_jury_panel_id', 'status'], 'tjpa_panel_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_jury_panel_assignments');
    }
};
