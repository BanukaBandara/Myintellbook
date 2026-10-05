<?php

namespace App\Providers;

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
    }
}
