<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\WorkExperiance;
use App\Observers\ExperianceObserver;
use App\Models\Education;
use App\Observers\EducationObserver;
use App\Models\Skill;
use App\Observers\SkillObserver;
use App\Observers\ExamObserver;
use App\Models\Exam;
use App\Models\Achievement;
use App\Models\TribunalReport;
use App\Models\ProfessionalVerification;
use App\Observers\AchievementObserver;
use App\Observers\TribunalReportObserver;
use App\Observers\ProfessionalVerificationScoreObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
       User::observe(UserObserver::class);
       WorkExperiance::observe(ExperianceObserver::class);
       Education::observe(EducationObserver::class);
       Skill::observe(SkillObserver::class);
       Exam::observe(ExamObserver::class);
       Achievement::observe(AchievementObserver::class);
       TribunalReport::observe(TribunalReportObserver::class);
       ProfessionalVerification::observe(ProfessionalVerificationScoreObserver::class);

       $this->configureRateLimiting();
    }

    /**
     * Named limiters used in routes/api.php. Authenticated traffic is keyed by user, so people
     * behind one shared IP don't throttle each other; anonymous traffic by IP.
     */
    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->id
            ? 'user:'.$request->user()->id
            : 'ip:'.$request->ip();

        // Default ceiling for every authenticated endpoint.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($byUserOrIp($request)));

        // Login endpoints: per IP, plus per target email so one account can't be sprayed
        // from many IPs. (Failed-password lockout itself lives in UserService/AdminAuthController.)
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(10)->by('email:'.sha1(strtolower((string) $request->input('email')))),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(6)->by('ip:'.$request->ip()),
            Limit::perHour(5)->by('email:'.sha1(strtolower((string) $request->input('email')))),
        ]);

        // Search and lookup endpoints are cheap to call and expensive to serve; also slows scraping.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by($byUserOrIp($request)));

        // Writes that create content or notify other people.
        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(20)->by($byUserOrIp($request)));

        RateLimiter::for('verification-email', fn (Request $request) => Limit::perHour(3)->by($byUserOrIp($request)));
    }
}
