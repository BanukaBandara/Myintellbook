<?php

namespace App\Observers;

use App\Models\Exam;
use App\Models\Question;
use App\Notifications\NewUserNotification;

class ExamObserver
{
    /**
     * Handle the Exam "created" event.
     */
    public function created(Exam $exam): void
    {
        $questions=Question::where('profession_id',$exam->profession_id)->where('difficulty_level',$exam->difficulty)->inRandomOrder()->limit(5)->get();
        $questionIds = $questions->pluck('id')->toArray();
        $exam->questions()->attach($questionIds);
        $exam->users()->attach(auth()->user()->id);

        $profession = \App\Models\profession::where('id',$exam->profession_id)->first();
        $profession->users()->attach(auth()->user()->id);
        auth()->user()->notify(new NewUserNotification("Exam created you can see from the learn tab!"));
        auth()->user()->notify(new NewUserNotification("This exam added to saved list for you!"));
    }

    /**
     * Handle the Exam "updated" event.
     */
    public function updated(Exam $exam): void
    {
        //
    }

    /**
     * Handle the Exam "deleted" event.
     */
    public function deleted(Exam $exam): void
    {
        //
    }

    /**
     * Handle the Exam "restored" event.
     */
    public function restored(Exam $exam): void
    {
        //
    }

    /**
     * Handle the Exam "force deleted" event.
     */
    public function forceDeleted(Exam $exam): void
    {
        //
    }
}
