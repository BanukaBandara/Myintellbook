<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Question;
use Illuminate\Support\Facades\Auth;
use App\Models\Answer;
use App\Models\Post;
use Carbon\Carbon;
use App\Http\Requests\AnswerRequest;
use App\Notifications\NewUserNotification;
use Illuminate\Support\Facades\Log;
use App\Events\ScoreEvent;

class QuestionController extends Controller
{

    public function setUserAnswer(AnswerRequest $request)
    {

          try{

            $user = Auth::user();

            $question = Question::where('id', $request->question_id)->first();

            // Assuming you have a method to save the user's answer

            Answer::create([
                'user_id' => $user->id,
                'question_id' => $request->question_id,
                'answer' => $request->answer,
                'answer_status' => ($question->answer == $request->answer) ? 'correct' : 'incorrect',
            ]);

            if($question->answer == $request->answer) {
                ScoreEvent::dispatch(7,$user->id,Question::class,$request->question_id);
            }
            auth()->user()->notify(new NewUserNotification("You have answer the today's question! answer will be publish tomorrow"));

        return response()->json(['code'=>200,'message' => 'Answer will publish tomorrow'], 200);

        }catch(\Exception $e){
            log::error('QuestionController @setUserAnswer: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
       
    }

}
