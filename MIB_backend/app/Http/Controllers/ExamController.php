<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExamCheckAnswerRequest;
use App\Http\Requests\ExamCreateRequest;
use App\Http\Requests\ExamDataRequest;
use App\Http\Requests\ExamListRequest;
use App\Http\Requests\ExamSaveRequest;
use App\Http\Requests\ExamSubmitAnswersRequest;
use App\Models\Exam;
use App\Models\profession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; 
use App\Notifications\NewUserNotification;

class ExamController extends Controller
{
    public function create(ExamCreateRequest $request)
    {

        try{

            $duration = $this->getDuration($request->NoQuestions);
            $totalMarks = $this->totalMarks($request->NoQuestions);
            $title= $this->title($request->level,$request->profession);
            $description= $this->description($request->level,$request->profession,$request->NoQuestions);

            Exam::create(
                [
                    'profession_id'=>$request->profession,
                    'difficulty' => $request->level,
                    'total_marks'=>$totalMarks,
                    'duration_minutes'=>$duration,
                    'title'=>$title,
                    'description'=>$description
                ]
                );

            return response()->json(['code'=>200,'message' => 'exam is created successfully'], 200);

        }catch(\Exception $e){
            log::error('ExamController @create: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    private function getDuration($numberOfQuestions)
    {
        $timeForOneQuestion = 2;

        return $timeForOneQuestion * $numberOfQuestions; 
    }

    private function totalMarks($numberOfQuestions)
    {
        $marksForOneQuestion = 5;
        return $marksForOneQuestion * $numberOfQuestions;
    }

    private function title($level,$profession_id)
    {
         $Cname = profession::where('id',$profession_id)->first()->name;
        return  $Cname ." ".ucfirst($level). " Exam";
    }

    private function description($level,$profession_id,$Qcount)
    {
        
        $Cname = profession::where('id',$profession_id)->first()->name;

       return "This is a " . $level . " level exam in " . $Cname .
            " containing " . $Qcount . " randomly selected questions.";
    }

    public function getExams(ExamListRequest $request)
    {
         try{

            $searchService = new \App\Services\SearchService();

             if($request->category == 0 && $request->serchKey == ''){
                 $exams = Exam::get();
            }else  if($request->category != 0 && $request->serchKey == ''){
                $exams = Exam::where('profession_id',$request->category)->get();
            }else if($request->category == 0 && $request->serchKey != ''){
                $exams = $searchService->serchByCategoryAll($request->serchKey);
            }else{
                $exams = $searchService->serchByCategoryAll($request->serchKey)->where('profession_id',$request->category);
            }

            // if($selectCat ==0){
            //     $exams = Exam::get();
            // }else{
            //     $exams = Exam::where('profession_id',$selectCat)->get();
            // }

            $formatedExams = $exams->map(function($detail){
                $total_questions = $detail->total_marks/5;
                return[
                    'id'=>$detail->id,
                    'title'=>$detail->title,
                    'description'=>$detail->description,
                    'duration_minutes'=>$detail->duration_minutes,
                    'total_marks'=>$detail->total_marks,
                    'total_questions'=>$total_questions,
                    'exam_taken'=> $this->isExamTaken($detail),
                    'difficulty'=>$detail->difficulty,
                    'profession'=>$detail->profession->name,
                    'created_at'=>Carbon::parse($detail->created_at)->diffForHumans(),
                    'isBooked'=>$detail->users()->where('user_id', auth()->user()->id)->exists(),
                    'saveButton'=>$detail->users()->where('user_id', auth()->user()->id)->exists() ? 'Remove':'Save',
                    'saveButtonIcon'=>$detail->users()->where('user_id', auth()->user()->id)->exists() ? 'pi pi-bookmark-fill':'pi pi-bookmark',
                    'saveButtonColor'=>$detail->users()->where('user_id', auth()->user()->id)->exists() ? 'danger':'info'
                ];
            });

            return response()->json(['code'=>200,'data' => $formatedExams->where('exam_taken',false)], 200);

        }catch(\Exception $e){
            log::error('ExamController @create: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    public function saveExam(ExamSaveRequest $request)
    {
        try{

            $exam = Exam::where('id',$request->ExamSavedIndex)->first();
            $profession = \App\Models\profession::where('id',$exam->profession_id)->first();
            $profession->users()->attach(auth()->user()->id);

            if($request->isBooked)
            {
                $exam->users()->attach(auth()->user()->id);
            }else{
                $exam->users()->detach(auth()->user()->id);
            }
            
            return response()->json(['code'=>200,'message' => 'Successfully add the exam'], 200);

        }catch(\Exception $e){
            log::error('ExamController @saveExam: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    public function getMyExams(ExamListRequest $request)
    {

        try{

            
            $user = User::find(auth()->user()->id);
            $searchService = new \App\Services\SearchService();


            if($request->category == 0 && $request->serchKey == ''){
                $exams = $user->exams;
            }else  if($request->category != 0 && $request->serchKey == ''){
                $exams = $user->exams->where('profession_id',$request->category);
            }else if($request->category == 0 && $request->serchKey != ''){
                $exams = $searchService->searchByCategory($request->serchKey,$user);
            }else{
                $exams = $searchService->searchByCategory($request->serchKey,$user)->where('profession_id',$request->category);
            }

            $formatedExams = $exams->filter(function($detail) {
                return $detail->pivot->attempted_at == null; // Keep only attempted exams
            })->map(function($detail){
                $total_questions = $detail->total_marks/5;
                return[
                    'id'=>$detail->id,
                    'title'=>$detail->title,
                    'description'=>$detail->description,
                    'duration_minutes'=>$detail->duration_minutes,
                    'total_marks'=>$detail->total_marks,
                    'total_questions'=>$total_questions,
                    'difficulty'=>$detail->difficulty,
                    'profession'=>$detail->profession->name,
                    'created_at'=>Carbon::parse($detail->created_at)->diffForHumans(),
                    'exam_taken'=> $detail->pivot->attempted_at ? true : false,
                    'score'=>$detail->pivot->score,
                    'passed'=>$detail->pivot->passed,
                    'isBooked'=>$detail->users()->where('user_id', auth()->user()->id)->exists()
                ];
            })->values();

            return response()->json(['code'=>200,'data' =>$formatedExams ], 200);

        }catch(\Exception $e){
            log::error('ExamController @getMyExams: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    public function getExamData(ExamDataRequest $request)
    {
        try{

            $exam = Exam::find($request->examId);

            $exams = $exam->questions()->get();

            $total_questions = $exam->total_marks/5;

           

            $formatedExams = [
                'id'=>$exam->id,
                'title' => $exam->title,
                'description'=>$exam->description,
                'duration'=> $exam->duration_minutes,
                'total_marks'=>$exam->total_marks,
                'total_questions'=>$total_questions,
                'isBooked'=>$exam->users()->where('user_id', auth()->user()->id)->exists(),
                'questions'=>$exam->questions->map(function($question)use($request){
                    return[
                        'id'=>$question->id,
                        'options'=>$this->formatOptions($question->QuesOptions,$request),
                        'question'=>$question->question,
                    ];
                })
            ];

            return response()->json(['code'=>200,'data' =>$formatedExams ], 200);

        }catch(\Exception $e){
            
            log::error('ExamController @getExamData: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    public function submitAnswers(ExamSubmitAnswersRequest $request)
    {
        try{

           $examId = $request['exam'];
           $scores = new  \App\Services\ScoreService();
           $answersJson = collect($request['answers'])->map(function($answer){
                return[
                    $answer['qId']=>$answer['answer']
                ];
           })->toJson();


           $total_marks = collect($request['answers'])->map(function($answer){
            
                $sum = 0;
                $question = \App\Models\Question::where('id',$answer['qId'])->first();

                if($question->answer == $answer['answer'])
                {
                    $sum = +5;
                }

                return $sum;
                
            })->sum();


            auth()->user()->exams()->sync([
                    $examId => ['score' => $total_marks,'attempted_at'=>Carbon::now(),'answers'=>$answersJson]
            ], false);

            $exam = Exam::where('id',$examId)->first();

            auth()->user()->notify(new NewUserNotification("You have successfully completed the exam $exam->title!"));
            return response()->json(['code'=>200,'data' =>'exam submited successfully' ], 200);

        }catch(\Exception $e){
            log::error('ExamController @submitAnswers: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    public function checkAnswer(ExamCheckAnswerRequest $request)
    {
        try{

            $question = \App\Models\Question::where('id',$request[1])->first();

            if($question->answer == $request[0])
            {
                return response()->json(['code'=>200,'data'=>["QuestionId"=>$request[1],"isCorrect"=>true,'CorrectAnswer'=>$question->answer] ], 200);
            }else{
                return response()->json(['code'=>200,'data' => ["QuestionId"=>$request[1],"isCorrect"=>false,'CorrectAnswer'=>$question->answer]], 200);
            }

        }catch(\Exception $e){
            log::error('ExamController @submitAnswers: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

     public function getSummary($examId)
    {
        try{

            $exam = \App\Models\Exam::find($examId);

            $examData = $exam->users()->where('user_id',auth()->user()->id)->get();

            $examData = $examData->first();
            $examIds = collect(json_decode($examData->pivot->answers))->map(function($answer){
                 return [
                    'questionId'=>key((array)$answer),
                    'userAnswer'=>current((array)$answer)
                 ];
            });
            $examSummary = [
                'score'=> $examData->pivot->score,
                'No_questions'=>$exam->total_marks/5,
                'questions'=>$examIds->map(function($qId) {
                        $question = \App\Models\Question::where('id',$qId['questionId'])->first();
                        return[
                            'id'=>$question->id,
                            'question'=>$question->question,
                            'correct_answer'=>$question->answer,
                            'user_answer'=>$qId['userAnswer'],
                            'isCorrect'=> $question->answer == $qId['userAnswer'] ? true : false,
                        ];
                    })
            ];
             return response()->json(['code'=>200,'data'=>$examSummary], 200);

        }catch(\Exception $e){
            log::error('ExamController @submitAnswers: '.$e->getMessage());
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => \App\Support\SafeError::message($e),
            ], 500);
        }
    }

    private function formatOptions($options,$request)
    {
        return collect($this->filterOptions($options,$request))->mapWithKeys(function($opt){
                return [$opt->label => $opt->value];
            
        });
    }

    private function filterOptions($options,$request)
    {
            if($request->from != 'exam')
            {
                 return $options->where('marks','!=',0); 
            }
            return $options;
    }

    private function isExamTaken($exam)
    {
        $examTaken = false;

        $examTaken = $exam->users()->get()->contains(function ($user) {
            return $user->id === auth()->user()->id && $user->pivot->attempted_at !== null;
        });

        return $examTaken;
    }
}
