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
        Schema::create('tribunal_case_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tribunal_case_id')
                ->constrained('tribunal_cases')
                ->cascadeOnDelete();

            $table->foreignId('respondent_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('acknowledgement_at')->nullable();
            $table->string('position', 30)->nullable();
            $table->text('response_text')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->unique('tribunal_case_id');
            $table->index('respondent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_case_responses');
    }
};
