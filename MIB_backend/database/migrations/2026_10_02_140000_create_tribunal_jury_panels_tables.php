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
        // 1. Tribunal Jury Panels
        if (!Schema::hasTable('tribunal_jury_panels')) {
            Schema::create('tribunal_jury_panels', function (Blueprint $table) {
                $table->id();
                $table->string('panel_code', 30)->unique();
                $table->string('panel_name', 255);
                $table->foreignId('login_user_id')
                    ->unique()
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->string('status', 30)->default('active');
                $table->foreignId('created_by')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->index('status');
                $table->index('created_by');
            });
        }

        // 2. Tribunal Jury Panel Events (Audit Log)
        if (!Schema::hasTable('tribunal_jury_panel_events')) {
            Schema::create('tribunal_jury_panel_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tribunal_jury_panel_id')
                    ->constrained('tribunal_jury_panels')
                    ->cascadeOnDelete();
                $table->foreignId('actor_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('event_type', 60);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('tribunal_jury_panel_id');
                $table->index('event_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_jury_panel_events');
        Schema::dropIfExists('tribunal_jury_panels');
    }
};
