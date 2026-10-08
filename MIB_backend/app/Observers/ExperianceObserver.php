<?php

namespace App\Observers;

use App\Models\WorkExperiance;
use App\Models\User;
use App\Notifications\NewUserNotification;
use App\Events\ScoreEvent;
use App\Services\HipScoreCalculator;

class ExperianceObserver
{
    /**
     * Handle the WorkExperiance "created" event.
     */
    public function created(WorkExperiance $workExperiance): void
    {
        $this->recalculateHipScore($workExperiance);

        if($workExperiance->positionType == 'executive')
        {
            ScoreEvent::dispatch(5,$workExperiance->user_id,WorkExperiance::class,$workExperiance->id,$workExperiance->diffrence);
             
        }else{
            ScoreEvent::dispatch(6,$workExperiance->user_id,WorkExperiance::class,$workExperiance->id,$workExperiance->diffrence);
        }
        $score = $workExperiance->score;
        $message = 'You have added a work experience detail!';
        if ($score) {
            $message .= ' You have gained a '.$score->calculated_score.' score to your Life Competency Index (LCI).';
        } else {
            $message .= ' Score calculation is unavailable until the scoring item is configured.';
        }

        $this->notifyExperienceUser($workExperiance, $message);
       
    }

    /**
     * Handle the WorkExperiance "updated" event.
     */
    public function updated(WorkExperiance $workExperiance): void
    {
        $this->recalculateHipScore($workExperiance);
        $this->notifyExperienceUser($workExperiance, 'You have updated a work experience detail.');
    }

    /**
     * Handle the WorkExperiance "deleted" event.
     */
    public function deleted(WorkExperiance $workExperiance): void
    {
        $this->recalculateHipScore($workExperiance);
        $this->notifyExperienceUser($workExperiance, 'Your work experience was deleted.');
    }

    /**
     * Handle the WorkExperiance "restored" event.
     */
    public function restored(WorkExperiance $workExperiance): void
    {
        $this->recalculateHipScore($workExperiance);
    }

    /**
     * Handle the WorkExperiance "force deleted" event.
     */
    public function forceDeleted(WorkExperiance $workExperiance): void
    {
        $this->recalculateHipScore($workExperiance);
    }

    private function notifyExperienceUser(WorkExperiance $workExperiance, string $message): void
    {
        $user = User::query()->find($workExperiance->user_id);
        if ($user) {
            $user->notify(new NewUserNotification($message));
        }
    }

    private function recalculateHipScore(WorkExperiance $workExperiance): void
    {
        $user = User::find($workExperiance->user_id);
        if ($user !== null) {
            HipScoreCalculator::recalculate($user);
        }
    }
}
