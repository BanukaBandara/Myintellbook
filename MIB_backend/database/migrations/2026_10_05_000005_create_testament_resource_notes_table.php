<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testament_resource_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->string('category', 80);
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('location', 160)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testament_resource_notes');
    }
};
