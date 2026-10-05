<?php

namespace App\Services;
use App\Models\skill;
use App\Models\Education;
use App\Models\WorkExperiance;
use App\Models\UserScore;

class ScoreFetchService{

    public function getAllScores(int $userId): array
    {

        return [
            // 'skills' => Skill::where('user_id', $userId)->with('score')->get(),
            'education' => $this->educationScore(Education::where('user_id', $userId)->with('score')->get()),
            'experience' => $this->experianceScore(WorkExperiance::where('user_id', $userId)->with('score')->get()),
            'totalScore' => $this->totalScore(),
        ];
    }

    private function educationScore($education)
    {
       $result = [];

        foreach($education as $edu)
        {
            $result[] =[
                'degree'=> $edu->degree,
                'category'=>$edu->category,
                'calculated_score'=>optional($edu->score)->calculated_score ?? 0
            ];
        }
           return $result; 
    }

    private function experianceScore($experiance)
    {
        $result = [];

        foreach($experiance as $exp)
        {
            $result[] = [
                'company'=>$exp->company,
                'position'=>$exp->positionType,
                'calculated_score'=>optional($exp->score)->calculated_score ?? 0
            ];
        }

        return $result;
    }

    public function totalScore()
    {
      return UserScore::where('user_id',auth()->user()->id)->sum('calculated_score');
    }

}