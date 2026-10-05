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
        Schema::create('tribunal_cases', function (Blueprint $table) {
            $table->id();

            $table->string('case_number')->nullable()->unique();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title', 180);

            $table->string('category', 100);

            $table->text('description');

            $table->text('requested_resolution')->nullable();

            $table->string('status', 40)
                ->default('submitted');

            $table->string('severity', 20)
                ->default('low');

            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_cases');
    }
};
