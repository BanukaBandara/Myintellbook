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
        // 1. Representation Requests
        if (!Schema::hasTable('tribunal_representation_requests')) {
            Schema::create('tribunal_representation_requests', function (Blueprint $table) {
                $table->id();

                $table->foreignId('tribunal_case_id')
                    ->constrained('tribunal_cases', indexName: 'trr_case_fk')
                    ->cascadeOnDelete();

                $table->foreignId('requested_by')
                    ->constrained('users', indexName: 'trr_req_by_fk')
                    ->cascadeOnDelete();

                $table->foreignId('client_user_id')
                    ->constrained('users', indexName: 'trr_client_fk')
                    ->cascadeOnDelete();

                $table->foreignId('representative_user_id')
                    ->constrained('users', indexName: 'trr_rep_fk')
                    ->cascadeOnDelete();

                $table->string('side', 30); // complainant, respondent
                $table->string('status', 30)->default('pending'); // pending, accepted, declined, cancelled, ended
                $table->text('message')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('responded_at')->nullable();
                $table->text('decline_reason')->nullable();
                $table->timestamps();

                $table->index('tribunal_case_id');
                $table->index('client_user_id');
                $table->index('representative_user_id');
                $table->index('status');
                $table->index('side');
            });
        }

        // 2. Representative Assignments (Active representation)
        if (!Schema::hasTable('tribunal_representative_assignments')) {
            Schema::create('tribunal_representative_assignments', function (Blueprint $table) {
                $table->id();

                $table->foreignId('tribunal_case_id')
                    ->constrained('tribunal_cases', indexName: 'tra_case_fk')
                    ->cascadeOnDelete();

                $table->foreignId('client_user_id')
                    ->constrained('users', indexName: 'tra_client_fk')
                    ->cascadeOnDelete();

                $table->foreignId('representative_user_id')
                    ->constrained('users', indexName: 'tra_rep_fk')
                    ->cascadeOnDelete();

                $table->string('side', 30); // complainant, respondent

                $table->unsignedBigInteger('representation_request_id')->nullable();
                $table->foreign('representation_request_id', 'tra_req_fk')
                    ->references('id')
                    ->on('tribunal_representation_requests')
                    ->nullOnDelete();

                $table->string('status', 30)->default('active'); // active, ended, revoked
                $table->timestamp('accepted_at');
                $table->timestamp('ended_at')->nullable();

                $table->unsignedBigInteger('ended_by')->nullable();
                $table->foreign('ended_by', 'tra_ended_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->text('end_reason')->nullable();
                $table->timestamps();

                $table->index('tribunal_case_id');
                $table->index('client_user_id');
                $table->index('representative_user_id');
                $table->index('status');
                $table->index('side');
            });
        }

        // 3. Private Client-Representative Conversations
        if (!Schema::hasTable('tribunal_conversations')) {
            Schema::create('tribunal_conversations', function (Blueprint $table) {
                $table->id();

                $table->foreignId('tribunal_case_id')
                    ->constrained('tribunal_cases', indexName: 'tc_case_fk')
                    ->cascadeOnDelete();

                $table->string('type', 50); // complainant_representative, respondent_representative

                $table->foreignId('client_user_id')
                    ->constrained('users', indexName: 'tc_client_fk')
                    ->cascadeOnDelete();

                $table->foreignId('representative_user_id')
                    ->constrained('users', indexName: 'tc_rep_fk')
                    ->cascadeOnDelete();

                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index('tribunal_case_id');
                $table->index('client_user_id');
                $table->index('representative_user_id');
                $table->index('active');
            });
        }

        // 4. Messages within Private Conversation
        if (!Schema::hasTable('tribunal_messages')) {
            Schema::create('tribunal_messages', function (Blueprint $table) {
                $table->id();

                $table->foreignId('conversation_id')
                    ->constrained('tribunal_conversations', indexName: 'tm_conv_fk')
                    ->cascadeOnDelete();

                $table->foreignId('sender_id')
                    ->constrained('users', indexName: 'tm_sender_fk')
                    ->cascadeOnDelete();

                $table->text('body');
                $table->string('attachment_path', 500)->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index('conversation_id');
                $table->index('sender_id');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribunal_messages');
        Schema::dropIfExists('tribunal_conversations');
        Schema::dropIfExists('tribunal_representative_assignments');
        Schema::dropIfExists('tribunal_representation_requests');
    }
};
