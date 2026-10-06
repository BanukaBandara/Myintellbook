<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\ApiTokenAuthMiddleware::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'jury.panel' => \App\Http\Middleware\EnsureJuryPanel::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command(\App\Console\Commands\AssignQuestionsForUsers::class)->dailyAt('00:00');
        $schedule->command(\App\Console\Commands\DailyPost::class)->dailyAt('00:00');
        $schedule->command(\App\Console\Commands\CalculateExperienceScore::class)->yearly();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
