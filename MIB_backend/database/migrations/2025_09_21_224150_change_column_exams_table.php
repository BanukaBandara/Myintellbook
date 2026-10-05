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
      Schema::table('exams', function (Blueprint $table) {
               // Drop old foreign key first
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');

            // Add new column with FK
            $table->foreignId('profession_id')
                  ->nullable()
                  ->constrained('professions')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
