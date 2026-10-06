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
];
