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
        Schema::table('internal_penalties', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('applied_at');
            $table->timestamp('ends_at')->nullable()->after('starts_at');

            $table->index(['user_id', 'action_type', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_penalties', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'action_type', 'ends_at']);
            $table->dropColumn(['ends_at', 'starts_at']);
        });
    }
};
