<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_answers', function (Blueprint $table) {
            $table->decimal('score', 8, 2)->nullable()->change();
            $table->date('answer_date')->nullable()->after('selected_option_index');
            $table->string('status', 32)->default('pending_evaluation')->after('score');
            $table->boolean('is_correct')->nullable()->after('status');
            $table->timestamp('evaluated_at')->nullable()->after('is_correct');
            $table->index(['status', 'answer_date']);
        });

        // Rows created before this migration were scored immediately on submission.
        DB::table('user_answers')->update([
            'status' => 'evaluated',
            'is_correct' => DB::raw('CASE WHEN score >= 5 THEN 1 ELSE 0 END'),
            'evaluated_at' => DB::raw('created_at'),
        ]);
        DB::table('user_answers')
            ->whereNull('answer_date')
            ->whereNotNull('created_at')
            ->orderBy('id')
            ->each(function ($row): void {
                DB::table('user_answers')
                    ->where('id', $row->id)
                    ->update(['answer_date' => substr((string) $row->created_at, 0, 10)]);
            });
    }

    public function down(): void
    {
        DB::table('user_answers')->whereNull('score')->delete();

        Schema::table('user_answers', function (Blueprint $table) {
            $table->dropIndex(['status', 'answer_date']);
            $table->dropColumn(['answer_date', 'status', 'is_correct', 'evaluated_at']);
            $table->decimal('score', 8, 2)->nullable(false)->change();
        });
    }
};
