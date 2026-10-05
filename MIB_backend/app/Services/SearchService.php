<?php

namespace App\Services;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\ExamResource;

class SearchService{

    public function searchKey($key)
    {
        $profiles = \App\Models\Profile::search($key)->keys(); // get matched IDs

        $profiles = \App\Models\Profile::select('profiles.*')
            ->join('users', 'profiles.user_id', '=', 'users.id')
            ->whereIn('profiles.id', $profiles)
            ->orderBy('users.rank', 'asc')
            ->with('user')
            ->whereNot('user_id',auth()->user()->id)
            ->get();

         return response()->json([
                'code' => 200,
                'status' => true,
                'data'=>ProfileResource::collection($profiles),
                'exams'=>$this->searchExam($key),
                'categories'=> $this->SearchCategory($key),
            ], 200);
    }

    public function searchExam($key){

        $exams = \App\Models\Exam::search($key)->keys();

        $exams = \App\Models\Exam::select('exams.*')
            ->join('professions', 'exams.profession_id', '=', 'professions.id')
            ->whereIn('exams.id', $exams)
            ->with('profession')
            ->get();

       return ExamResource::collection($exams);
    }

    public function searchByCategory($key,$user)
    {
        $exams = \App\Models\profession::search($key)->keys();
        $exams = $user->exams()->whereIn('profession_id', $exams)
                ->with(['profession'])
                ->get();

       return $exams;
    }

    public function serchByCategoryAll($key)
    {
        $exams = \App\Models\profession::search($key)->keys();
        $exams = \App\Models\Exam::whereIn('profession_id', $exams)
                ->with(['profession'])
                ->get();

       return $exams;
    }

    public function SearchCategory($key)
    {
        $categories = \App\Models\profession::search($key)->get();

        return $categories->map(function($categories){
            return 
            [
                'id'=>$categories->id,
                'name'=>$categories->name,
                'isFollowed'=> auth()->user()->professions()->where('profession_id',$categories->id)->exists()
            ];
        });
    }
}