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
        Schema::table('user_scores', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('scoring_item_id');
            $table->string('source_id')->nullable()->after('source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_scores', function (Blueprint $table) {
            //
        });
    }
};
