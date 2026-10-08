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
        Schema::create('tribunal_case_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_id')->constrained('tribunal_cases')->cascadeOnDelete();
            $table->foreignId('tribunal_decision_id')->constrained('tribunal_decisions')->cascadeOnDelete();
            $table->string('report_number')->unique();
            $table->string('verification_code')->unique()->index();
            $table->string('report_type')->default('final_decision_report');
            $table->string('status')->default('generated'); // generated, superseded, revoked
            $table->unsignedInteger('version')->default(1);
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->dateTime('issued_at');
            $table->foreignId('generated_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('generated_at');
            $table->dateTime('last_downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['tribunal_case_id', 'status']);
            $table->index(['tribunal_case_id', 'version']);
        });

        Schema::create('tribunal_case_report_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribunal_case_report_id')->constrained('tribunal_case_reports')->cascadeOnDelete();
            $table->foreignId('downloaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('downloaded_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('tribunal_case_report_id');
            $table->index('downloaded_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_case_report_downloads');
        Schema::dropIfExists('tribunal_case_reports');
    }
};
