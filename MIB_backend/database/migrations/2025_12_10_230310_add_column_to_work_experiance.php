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
        Schema::table('work_experiances', function (Blueprint $table) {
            $table->string('starting_date')->nullable()->after('location');
            $table->string('end_date')->nullable()->after('starting_date');
            $table->string('positionType')->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_experiance', function (Blueprint $table) {
            //
        });
    }
};
