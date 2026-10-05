<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Question;
use App\Notifications\NewUserNotification;
use Carbon\Carbon;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $question = Question::inRandomOrder()
                ->first();
        if ($question) {
            $question->update([
                'user_id' => $user->id,
                'issue_date' => now(),          
            ]);
        $user->questionsSeen()->attach($question->id);
        $questionFormated = json_encode([
                'id' => $question->id,
                'question' => $question->question,
                'options' => $question->options,
                'answer' => $question->answer,
                'difficulty_level' => $question->difficulty_level,
                'points' => 0,
                'is_approved' => false,
            ]);

        $post = \App\Models\Post::create([
                'user_id' => $user->id,
                'content' => $questionFormated,
                'question_id' => $question->id,
                'posting_date' => Carbon::now(),
            ]);
        } else {
            // Handle the case where no question is available
            // You might want to log this or take some other action     
        }
        $user->notify(new NewUserNotification("Hi $user->first_name, 'Welcome to MyIntellBook! We are excited to have you on board. Start your journey of knowledge and growth with us today.'"));
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
