<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedTinyInteger('selected_option_index')->nullable()->after('question_id');
            $table->decimal('score', 8, 2)->nullable()->after('answer_status');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn(['selected_option_index', 'score']);
        });
    }
};