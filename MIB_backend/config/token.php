<?php

/*
    token config
*/
return [
    /*
            set token expiration time in days
    */
    'expires_at'=> env('TOKEN_EXPIRES_AT', 8),

    /*
            Admin sessions carry full control of the platform, so they expire much sooner
            (minutes). Admins simply sign in again.
    */
    'admin_expires_minutes' => (int) env('ADMIN_TOKEN_EXPIRES_MINUTES', 480),

    /*
            Failed sign-in lockout: after this many wrong passwords for one email,
            further attempts for that email are refused for the decay period (seconds).
    */
    'login_max_failures' => (int) env('LOGIN_MAX_FAILURES', 5),
    'login_lockout_seconds' => (int) env('LOGIN_LOCKOUT_SECONDS', 900),

    /*
            Password reset links are valid for this many minutes after they are issued.
    */
    'password_reset_expires_minutes' => (int) env('PASSWORD_RESET_EXPIRES_MINUTES', 60),
];
