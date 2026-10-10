<?php

use Carbon\Carbon;
use App\Models\User;
use App\Models\WorkExperiance;
use App\Models\Education;

function userDataFormatting($profileDetails)
{

     $Details = $profileDetails->map(function($detail){

            //   $options = $detail->question->QuesOptions->map(function($opt){
            //       return [$opt->label]  = $opt->value;
            //   });
              $postService = new \App\Services\PostService();
              $lci = \App\Services\HipRankMatrix::forUser($detail);
              $hipScore = $lci['lci_score'];
              $format_question = [];

              if($detail->question !=null)
              {
                    $options = $detail->question->options;
                    if (!is_array($options) || $options === []) {
                        $options = $detail->question->QuesOptions
                            ->map(fn ($option) => $option->value)
                            ->values()
                            ->all();
                    }
                    $format_question = [
                        'id' => $detail->question->id,
                        'category' => $detail->question->category ?: $detail->question->profession?->name,
                        'question' => $detail->question->question,
                        'options' => $options,
                        'is_used' => $detail->question->is_used,
                        'difficulty_level' => $detail->question->difficulty_level,
                        'post_at'=> Carbon::parse($detail->question->issue_date)->diffForHumans()
                ];
              }
            

                return [
                    'first_name'=>$detail->profile['first_name'],
                    'full_name'=>$detail->profile['full_name'],
                    'last_name'=>$detail->profile['last_name'],
                    'profile_image'=>($detail->profile['profile_image']) ? $detail->profile['profile_image'] : '',
                    'cover_image'=>($detail->profile['cover_image']) ? $detail->profile['cover_image'] : '',
                    'total_points'=>$detail->total_points,
                    'hip_score'=>$hipScore,
                    'Hip'=>$hipScore,
                    'lci_score'=>$hipScore,
                    'hip_rank'=>$lci['hip_rank'],
                    'rank_tier'=>$lci['rank_tier'],
                    'rank_badge_color'=>$lci['rank_badge_color'],
                    'rank'=>$detail->Rank,
                    'school'=>getSchool($detail->id),
                    'profession'=>getProfession($detail->id),
                    'posts'=>$postService->postDetails($detail->posts),
                    'tquestion' => ($format_question) ? $format_question :[],
                ];
        });

        return response()->json([
                'code' => 200,
                'status' => true,
                'data' => $Details,
            ], 200);
}

function getProfession($id)
{
    $profession = WorkExperiance::where('user_id', $id)
        ->orderByDesc('currently_working')
        ->orderByDesc('updated_at')
        ->orderByDesc('id')
        ->first();

    if(!$profession)
        return[
        'company'=>'',
        'location'=>'',
        'profession'=>''
        ];
        
    return[
        'company'=>$profession->company,
        'location'=>$profession->location,
        'profession'=>$profession->title
    ];
}

function getSchool($id)
{
    $school = Education::where('user_id',$id)->first();

    $result = ($school) ? $school->school : '';
    return $result;
}

function formatUserInfo($profileDetails)
{
   return $profileDetails->map(function($detail){

                    $visibility = formatvisibility($detail->getSettings());

                    // Remove "Only Me" fields unless the viewer owns this profile.
                    return \App\Support\ProfileVisibility::filter([
                        'id'=>$detail->id,
                        'first_name'=>$detail->profile['first_name'],
                        'last_name'=> $detail->profile['last_name'],
                        'full_name'=> $detail->profile['full_name'],
                        'birth_date'=> $detail->profile['birth_date'],
                        'gender' => $detail->profile['gender'],
                        'profile_image'=> ($detail->profile['profile_image']) ? $detail->profile['profile_image'] : '',
                        'cover_image'=>($detail->profile['cover_image']) ? $detail->profile['cover_image'] : '',
                        'total_points'=>$detail->total_points,
                        'hip_score'=>\App\Services\HipScoreCalculator::recalculate($detail),
                        'rank'=>$detail->Rank,
                        'school'=>getSchool($detail->id),
                        'profession'=>getProfession($detail->id),
                        'experiance'=>$detail->workExperiances,
                        'education'=>$detail->educations,
                        'completed_exams'=>formatExamInfo($detail->exams()->wherePivotNotNull('score')->get()),
                        'upcomming_exams'=>formatExamInfo($detail->exams()->wherePivotNull('score')->get()),
                        'skills'=> $detail->skills,
                        'visibility'=>$visibility,
                        'profile_url'=>$detail->profile['full_url']
                    ], $visibility, (int) auth()->id() === (int) $detail->id);
                });
}

function formatExamInfo($examDetails)
{
   return $examDetails->map(function($detail){

                    return [
                        'id'=>$detail->id,
                        'title'=>$detail->title,
                        'description'=> $detail->description,
                        'duration_minutes'=> $detail->duration_minutes,
                        'total_marks'=>$detail->total_marks,
                        'difficulty'=>$detail->difficulty,
                        'profession'=>$detail->profession->name,
                        'percentage'=> calculatePercentage($detail->pivot->score,$detail->total_marks),
                        'passed'=> ($detail->pivot->passed) ? $detail->pivot->passed : false,
                        'created_at'=> formatDate($detail->pivot->created_at),
                        'attempted_at'=> ($detail->pivot->attempted_at) ? formatDate($detail->pivot->attempted_at): '',
                        'questions_count'=>$detail->questions()->count(),
                    ];
                });
}

function formatDate($date)
{
    return Carbon::parse($date)->format('M d, Y');
}

function calculatePercentage($score, $total)
{
    if($total == 0) return 0;
    return round(($score / $total) * 100, 2);
}

function formatvisibility($settings)
{
    $result =[];
     foreach ($settings as $item) {
                $result[$item['Key']] = $item['value'];
            }

    return $result;

}