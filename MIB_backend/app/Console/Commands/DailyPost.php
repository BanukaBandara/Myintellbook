<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Support\Facades\Log;
use App\Models\Post;
use App\Models\Score;
use App\Notifications\NewUserNotification;
class DailyPost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:daily-post';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $users = \App\Models\User::get();
        $postService = new \App\Services\PostService();

        foreach ($users as $key => $user) {
            
            
            $getYesterDayQuestion = Question::where('user_id',$user->id)
                                        ->whereDate('issue_date',$yesterday)
                                        ->first();


            if($getYesterDayQuestion){
                $scores = new  \App\Services\ScoreService();
                $date = Carbon::now();
                $base_type = 0;

                $answer = Answer::where('user_id', $user->id)
                ->latest('id')
                ->first();

                if (!$answer) {
                    $this->error('No answer found for user ID: ' . $user->id);

                    
                    $postService->updatePost($getYesterDayQuestion, $user, 'No answer',$yesterday);
                    $user->notify(new NewUserNotification("You havent answer the question ! No points add to your score"));
                    continue;
                }

                    $points = ($answer->answer_status == 'correct') ? 5 : 0;
                    
                    
                    $postService->updatePost($getYesterDayQuestion, $user, ($answer)? $answer->status : 'No answer',$yesterday);
            }

            
        }
        Log::info('post created ran at ' . now());
        $this->info('Daily post approved successfully.');
    }
}
