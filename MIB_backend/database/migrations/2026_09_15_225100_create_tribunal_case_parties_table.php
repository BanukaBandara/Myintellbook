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
        Schema::create('tribunal_case_parties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tribunal_case_id')
                ->constrained('tribunal_cases')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('role', 30);

            $table->timestamps();

            $table->unique([
                'tribunal_case_id',
                'user_id',
                'role',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_case_parties');
    }
};
