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
        Schema::create('scoring_items', function (Blueprint $table) {
            $table->id();
             $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_score', 10, 2);
            $table->enum('rule_type', ['fixed','per_day','per_exam','percentage','custom']);
            $table->decimal('max_score', 10, 2)->nullable();
            $table->decimal('min_score', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_items');
    }
};
