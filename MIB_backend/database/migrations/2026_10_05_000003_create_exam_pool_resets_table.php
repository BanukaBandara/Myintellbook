<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marks where a user's unseen-question cycle restarts. Exam sessions are kept (their
        // scores feed the HIP score); only sessions started after the latest reset count as "seen".
        Schema::create('exam_pool_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('category_id');
            $table->dateTime('reset_at');
            $table->unsignedSmallInteger('pool_size');
            $table->timestamps();
            $table->index(['user_id', 'category_id', 'reset_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_pool_resets');
    }
};
