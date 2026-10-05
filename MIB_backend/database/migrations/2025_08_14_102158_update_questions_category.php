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
        Schema::table('questions', function (Blueprint $table) {
            // Rename column
            $table->renameColumn('category', 'profession_id');
              $table->unsignedBigInteger('profession_id')->change();
             $table->foreign('profession_id')
                  ->references('id')
                  ->on('professions')
                  ->onDelete('cascade');

            // Add new column
            $table->string('difficulty_level')->nullable()->after('question');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            //
        });
    }
};
