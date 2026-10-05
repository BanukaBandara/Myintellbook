<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Artisan::command('app:assign-questions-for-users', function () {
//     $this->call(\App\Console\Commands\AssignQuestionsForUsers::class);
// });
