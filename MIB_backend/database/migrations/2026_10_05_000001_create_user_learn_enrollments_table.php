<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_learn_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('category_id');
            // The question set is fixed at enrollment so it stays the same for the whole 7-day pass.
            $table->json('question_ids');
            $table->string('status', 16)->default('active');
            $table->dateTime('enrolled_at');
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_learn_enrollments');
    }
};
