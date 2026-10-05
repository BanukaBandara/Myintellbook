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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade'); // if user deleted, settings also deleted

            $table->string('key')->index();   // e.g. "show_email", "theme"
            $table->text('value')->nullable(); // flexible: can store string, bool, JSON, etc.

            // prevent duplicate keys per user
            $table->unique(['user_id', 'key']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
