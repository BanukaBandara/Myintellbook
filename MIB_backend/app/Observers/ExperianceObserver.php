<?php

namespace App\Observers;

use App\Models\WorkExperiance;
use App\Models\Score;
use App\Notifications\NewUserNotification;
use Carbon\Carbon;
use App\Events\ScoreEvent;

class ExperianceObserver
{
    /**
     * Handle the WorkExperiance "created" event.
     */
    public function created(WorkExperiance $workExperiance): void
    {
        $date = Carbon::now();
        $user = auth()->user();
        if($workExperiance->positionType == 'executive')
        {
            ScoreEvent::dispatch(5,$user->id,WorkExperiance::class,$workExperiance->id,$workExperiance->diffrence);
             
        }else{
            ScoreEvent::dispatch(6,$user->id,WorkExperiance::class,$workExperiance->id,$workExperiance->diffrence);
        }
        $score = $workExperiance->score;
        auth()->user()->notify(new NewUserNotification( $user->first_name."You have added an Experiance detail! You have gain a ".$score->calculated_score." score to your  Life Competency Index(LCI) "));
       
    }

    /**
     * Handle the WorkExperiance "updated" event.
     */
    public function updated(WorkExperiance $workExperiance): void
    {
        $user = auth()->user()->id;

        auth()->user()->notify(new NewUserNotification($user->first_name."You have updated an Experiance detail!"));
    }

    /**
     * Handle the WorkExperiance "deleted" event.
     */
    public function deleted(WorkExperiance $workExperiance): void
    {
        $date = Carbon::now();
       auth()->user()->notify(new NewUserNotification("Your Experiance Deleted!"));
    }

    /**
     * Handle the WorkExperiance "restored" event.
     */
    public function restored(WorkExperiance $workExperiance): void
    {
        //
    }

    /**
     * Handle the WorkExperiance "force deleted" event.
     */
    public function forceDeleted(WorkExperiance $workExperiance): void
    {
        //
    }
}
