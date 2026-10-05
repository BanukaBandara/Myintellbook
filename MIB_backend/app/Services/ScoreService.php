<?php

namespace App\Services;
use App\Notifications\NewUserNotification;
use App\Models\Score;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Question;
use App\Models\Post;

class ScoreService
{
    public function __construct()
    {
        $this->today = Carbon::today()->toDateString();
    }
    
    public function notification_exsists($scores,$message, $index,$user){

            $exists = $user->notifications()
            ->where('type', NewUserNotification::class)
            ->where('data->message', $message)
            ->exists();

            return $index < 4 && !$exists;
                    
    }

    public function addScore($user,$activity_id,$activity_type,$points,$base_type=0,$date)
    {
         Score::create([
                        'user_id' => $user->id,
                        'activity_id' => $activity_id->id,
                        'activity_type' => $activity_type,
                        'points' => $points,
                        'base_type'=>$base_type,
                        'date' => $date,
                    ]);
    }

    public function getTopScores()
    {
        $scores = Score::with('user')->select('user_id', DB::raw('SUM(points) as total_points'))
                    ->whereHas('user.profile', function ($q) {
                        $q->whereNotNull('first_name');
                        })
                    ->groupBy('user_id')
                    ->orderBy('total_points', 'desc')
                    ->limit(6)
                    ->get();

       return $this->formatTopScores($scores);  
    }

    public function getScores($user_id,$activity_id,$activity_type)
    {
        return Score::where('user_id',$user_id)
                    ->where('activity_id',$activity_id)
                    ->where('activity_type',$activity_type)
                    ->first();
    }


    private function formatTopScores($scores)
    {
        return $scores->map(function($score){
                return[
                    'name'=>($score['user']['profile']) ? $score['user']['profile']->first_name . ' ' .$score['user']['profile']->last_name : '',
                    'profile_image'=>($score['user']['profile']) ? $score['user']['profile']->profile_image: '',
                    'rank'=>($score->user) ? $score->user->Rank : null,
                    'score'=>$score->total_points
                ];
            });
    }

    public function formatUserScores(){

        $user = auth()->id();
        

        $scores = Score::where('user_id',$user)->with('activity')->get();

        $formatedScore = $scores->groupBy('base_type');

        return [
            'daily_questions'=>$this->getscoreByQuestionType($formatedScore),
            'profile_update'=>$this->getscoreByProfileUpdateType($formatedScore),
            'exam'=> $this->getScoreByExamType($formatedScore),
            'totalScore'=> Score::where('user_id',$user)->sum('points')
        ];
    }

    private function getscoreByQuestionType($formatedScore){

        $today = $this->today;

       return $formatedScore->get(0,collect())->map(function($score)use($today){

               return [
                    'id' => $score->id,
                    'activity_id' => $score->activity_id,
                    'question' => Question::where('id',$score->activity_id)->first()->question,
                    'activity_type' => $score->activity_type,
                    'question_date'=>Post::with('question')
                                    ->where('user_id',auth()->id())
                                    ->whereDate('posting_date','<',$today)
                                    ->first()
                                    ->posting_date,
                    'points' => $score->points,
                    'daily_total'=>Score::where('base_type',0)->sum('points'),
                    
               ];
            });
    }

    private function getscoreByProfileUpdateType($formatedScore){

       return $formatedScore->get(1,collect())->map(function($score){


                if($score->activity_type == 'App\Models\Education'){
                    $name= "Education";
                }else if($score->activity_type == 'App\Models\WorkExperiance'){
                    $name="Experiance";
                }else if($score->activity_type == 'App\Models\Skill'){
                    $name="Skill";
                }
               return [
                    'id' => $score->id,
                    'activity_id' => $score->activity_id,
                    'added_date'=>$score->date,
                    'name' => $name,
                    'activity_type' => $score->activity_type,
                    'points' => $score->points,
                    'profile_total'=>Score::where('base_type',1)->sum('points')
               ];
            })->groupBy('name');
    }

    private function getScoreByExamType($formatedScore){

       return $formatedScore->get(2,collect())->map(function($score){

               return [
                    'id' => $score->id,
                    'activity_id' => $score->activity_id,
                    'exam' => $score->activity->title,
                    'diffuculty_level' => $score->activity->difficulty,
                    'activity_type' => $score->activity_type,
                    'points' => $score->points,
                    'exam_total'=>Score::where('base_type',2)->sum('points')
               ];
            }); 

    
    }

}