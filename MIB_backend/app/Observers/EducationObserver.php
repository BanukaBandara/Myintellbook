<?php

namespace App\Observers;

use App\Models\Education;
use App\Models\User;
use App\Models\Score;
use App\Notifications\NewUserNotification;
use Carbon\Carbon;
use App\Events\ScoreEvent;
use App\Services\ProfileService;
use App\Services\HipScoreCalculator;

class EducationObserver
{
    /**
     * Handle the Education "created" event.
     */
    public function created(Education $education): void
    {
        $this->recalculateHipScore($education);

        
        $eduType = $education->category;

        $profile= new ProfileService();
        $profile->StoreScore($education);
        $date = Carbon::now();
        if($eduType == "PhD")
        {
            $this->notifyEducationUser($education, "You have added a $eduType! Please verify the details with the tribunal.");
        }else{
            $score = $education->score;
            $message = "You have added an education detail!";
            if ($score) {
                $message .= " You have gained a ".$score->calculated_score." score to your Life Competency Index (LCI).";
            } else {
                $message .= " Score calculation is unavailable until the scoring item is configured.";
            }
            $this->notifyEducationUser($education, $message);
        }
        
        
    }

    /**
     * Handle the Education "updated" event.
     */
    public function updated(Education $education): void
    {
        $this->recalculateHipScore($education);

        $this->notifyEducationUser($education, 'You have updated an education detail.');
        
    }

    /**
     * Handle the Education "deleted" event.
     */
    public function deleted(Education $education): void
    {
        $this->recalculateHipScore($education);
        $date = Carbon::now();
        $this->notifyEducationUser($education, 'You have deleted an education detail.');
    }

    /**
     * Handle the Education "restored" event.
     */
    public function restored(Education $education): void
    {
        $this->recalculateHipScore($education);
    }

    /**
     * Handle the Education "force deleted" event.
     */
    public function forceDeleted(Education $education): void
    {
        $this->recalculateHipScore($education);
        $date = Carbon::now();
        $this->notifyEducationUser($education, 'You have deleted an education detail.');
    }

    private function notifyEducationUser(Education $education, string $message): void
    {
        $user = User::query()->find($education->user_id);
        if ($user) {
            $user->notify(new NewUserNotification($message));
        }
    }

    private function recalculateHipScore(Education $education): void
    {
        $user = User::find($education->user_id);
        if ($user !== null) {
            HipScoreCalculator::recalculate($user);
        }
    }
}
