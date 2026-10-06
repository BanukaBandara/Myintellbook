<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tribunal Appeal Window Duration
    |--------------------------------------------------------------------------
    |
    | Number of days allowed for parties to file an appeal following the
    | publication of a final Tribunal decision. Default is 14 days.
    |
    */
    'appeal_window_days' => (int) env('TRIBUNAL_APPEAL_WINDOW_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Tribunal Report Verification URL Base
    |--------------------------------------------------------------------------
    |
    | Base URL used for generating QR codes and verification links on official reports.
    |
    */
    'report_verify_url' => env('TRIBUNAL_REPORT_VERIFY_URL', rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/tribunal/reports/verify'),
];
