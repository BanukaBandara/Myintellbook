<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Security headers on every response (API and web).
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'auth.token' => \App\Http\Middleware\ApiTokenAuthMiddleware::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'jury.panel' => \App\Http\Middleware\EnsureJuryPanel::class,
            'restrict.feature' => \App\Http\Middleware\EnsureFeatureNotRestricted::class,
            'verified.email' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Runs first so DailyPost sees yesterday's evaluated answers.
        $schedule->command(\App\Console\Commands\EvaluateDailyQuestions::class)->dailyAt('00:00')->withoutOverlapping();
        $schedule->command(\App\Console\Commands\AssignQuestionsForUsers::class)->dailyAt('00:00');
        $schedule->command(\App\Console\Commands\DailyPost::class)->dailyAt('00:00');
        $schedule->command(\App\Console\Commands\CalculateExperienceScore::class)->yearly();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Unified API error responses. Validation (422), authentication (401) and deliberate
        // abort()/HTTP errors keep their intended messages; everything else is reduced to a
        // generic message with a reference that matches the log entry. With APP_DEBUG=true the
        // framework's detailed output is left alone for local development.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (!$request->is('api/*') || !($e->getPrevious() instanceof ModelNotFoundException)) {
                return null;
            }

            // Default text names the model class ("No query results for model [App\Models\...]").
            return response()->json([
                'status' => false,
                'message' => 'The requested resource was not found.',
            ], 404);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') || config('app.debug')) {
                return null;
            }
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpExceptionInterface) {
                return null;
            }

            $reference = (string) Str::uuid();
            Log::error('Unhandled API exception', [
                'reference' => $reference,
                'exception' => $e,
                'path' => $request->path(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong. Please try again.',
                'reference' => $reference,
            ], 500);
        });
    })->create();
