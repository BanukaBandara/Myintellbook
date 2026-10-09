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
            if (!Schema::hasColumn('internal_penalties', 'penalty_value')) {
                $table->string('penalty_value', 100)->nullable()->after('action_type');
            }

            $table->index(['user_id', 'action_type', 'penalty_value', 'ends_at'], 'ip_user_action_val_ends_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_penalties', function (Blueprint $table) {
            $table->dropIndex('ip_user_action_val_ends_idx');
            if (Schema::hasColumn('internal_penalties', 'penalty_value')) {
                $table->dropColumn('penalty_value');
            }
        });
    }
};
