<?php

namespace App\Observers;

use App\Models\Education;
use App\Models\Score;
use App\Notifications\NewUserNotification;
use Carbon\Carbon;
use App\Events\ScoreEvent;
use App\Services\ProfileService;

class EducationObserver
{
    /**
     * Handle the Education "created" event.
     */
    public function created(Education $education): void
    {
        
        $eduType = $education->category;

        $profile= new ProfileService();
        $profile->StoreScore($education);
        $user = auth()->user();

        $date = Carbon::now();
        if($eduType == "PhD")
        {
            auth()->user()->notify(new NewUserNotification($user->first_name."You have added a $eduType! please verify the details by tribunal"));
        }else{
            $score = $education->score;
            auth()->user()->notify(new NewUserNotification( $user->first_name."You have added an education detail! You have gain a ".$score->calculated_score." score to your  Life Competency Index(LCI) "));
        }
        
        
    }

    /**
     * Handle the Education "updated" event.
     */
    public function updated(Education $education): void
    {

        $user = auth()->user();
        auth()->user()->notify(new NewUserNotification($user->first_name."You have updated an Education detail!"));
        
    }

    /**
     * Handle the Education "deleted" event.
     */
    public function deleted(Education $education): void
    {
        $date = Carbon::now();
        auth()->user()->notify(new NewUserNotification("You have Deleted a Education detail!"));
    }

    /**
     * Handle the Education "restored" event.
     */
    public function restored(Education $education): void
    {
        //
    }

    /**
     * Handle the Education "force deleted" event.
     */
    public function forceDeleted(Education $education): void
    {
        $date = Carbon::now();
        auth()->user()->notify(new NewUserNotification("You have Deleted a Education detail!"));
    }
}
