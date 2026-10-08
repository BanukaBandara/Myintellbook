<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('achievements')) {
            Schema::create('achievements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('title');
                $table->string('category');
                $table->string('verification_status')->default('verified');
                $table->timestamps();
            });

            return;
        }

        if (! Schema::hasColumn('achievements', 'title')) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->string('title')->default('');
            });

            if (Schema::hasColumn('achievements', 'category')) {
                DB::table('achievements')->where('title', '')->update([
                    'title' => DB::raw('category'),
                ]);
            }
        }

        if (Schema::hasColumn('achievements', 'verification_status')) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->string('verification_status')->default('verified')->change();
            });
        } else {
            Schema::table('achievements', function (Blueprint $table) {
                $table->string('verification_status')->default('verified');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('achievements') && Schema::hasColumn('achievements', 'title')) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }
    }
};
