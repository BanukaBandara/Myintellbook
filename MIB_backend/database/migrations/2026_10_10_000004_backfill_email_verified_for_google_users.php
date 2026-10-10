<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sensitive actions now require a verified email (EnsureEmailIsVerified). Accounts created
 * through Google sign-in never had email_verified_at set, although Google had verified the
 * address, so mark them verified rather than locking existing Google users out.
 * Password accounts are left as they are: they can request a new link via
 * POST /api/email/verification-notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('google_id')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Irreversible data fix: we can't tell which rows this migration changed.
    }
};
