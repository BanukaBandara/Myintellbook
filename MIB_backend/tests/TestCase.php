<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sensitive routes require a verified email (EnsureEmailIsVerified). Most tests create users
        // with User::create() and don't care about verification, so treat new test users as
        // verified. Tests of the unverified path set email_verified_at back to null explicitly.
        User::creating(function (User $user): void {
            $user->email_verified_at ??= now();
        });
    }
}
