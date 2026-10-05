<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('draft');
            // Sensitive content: encrypted with the app key (AES-256-CBC) via Eloquent encrypted casts.
            $table->text('title')->nullable();
            $table->longText('instructions')->nullable();
            $table->text('health_declaration')->nullable();
            $table->text('beneficiaries')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->foreignId('witness_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('witness_status', 16)->nullable();
            $table->dateTime('witness_responded_at')->nullable();
            $table->dateTime('consent_given_at')->nullable();
            $table->string('tribunal_status', 24)->default('not_submitted');
            $table->dateTime('sealed_at')->nullable();
            $table->dateTime('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['witness_user_id', 'witness_status']);
        });

        // Append-only and hash-chained: each entry's hash covers the previous entry's hash.
        Schema::create('testament_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('testament_id')->constrained()->cascadeOnDelete();
            $table->string('event', 48);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Text, not JSON: MySQL's JSON type reorders keys, which would break hash verification.
            $table->text('details')->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64);
            $table->dateTime('created_at');
            $table->index(['testament_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testament_audit_logs');
        Schema::dropIfExists('testaments');
    }
};
